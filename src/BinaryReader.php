<?php

namespace PhpBinaryReader;

class BinaryReader
{
    /**
     * @var string
     */
    private $str;

    /**
     * @var int
     */
    private $currentBit;

    /**
     * @var mixed
     */
    private $currentByte;

    /**
     * @var int
     */
    private $position;

    /**
     * @var int
     */
    private $eofPosition;

    /**
     * @var array
     */
    private $loMasks = [0x00, 0x01, 0x03, 0x07, 0x0F, 0x1F, 0x3F, 0x7F, 0xFF];

    /**
     * @var array
     */
    private $hiMasks = [];

    /**
     * @var array
     */
    private $bitMasks = [];

    /**
     * @var int
     */
    private $endian;

    /**
     * @param  string $str
     * @param  string $endian
     * @throws \InvalidArgumentException
     */
    public function __construct($str, $endian = 'big')
    {
        $this->endian = $endian;
        if ($this->endian != 'big' && $this->endian != 'little') {
            throw new \InvalidArgumentException('An endian of big or little must be set');
        }

        $this->str = $str;
        $this->eofPosition = strlen($str);
        $this->currentByte = 0;
        $this->position = 0;
        $this->currentBit = 0;

        foreach ($this->loMasks as $loMask) {
            $hiMask = 0xFF ^ $loMask;
            $this->hiMasks[] = $hiMask;
            $this->bitMasks[] = [$loMask, $hiMask];
        }
    }

    /**
     * @param int $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @return int
     */
    public function getEofPosition()
    {
        return $this->eofPosition;
    }

    /**
     * @return int
     */
    public function getEndian()
    {
        return $this->endian;
    }

    /**
     * @return int
     */
    public function getCurrentBit()
    {
        return $this->currentBit;
    }

    /**
     * @return bool
     */
    public function isEof()
    {
        if ($this->position >= $this->eofPosition) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param  boolean $move
     * @return void
     */
    public function align($move = true)
    {
        if ($this->currentBit > 0 && $move) {
            $this->position++;
        }

        $this->currentBit = 0;
        $this->currentByte = false;
    }

    /**
     * @param  int $count
     * @return mixed
     * @throws \OutOfBoundsException
     */
    public function readBits($count)
    {
        if (($count / 8) + $this->position > $this->eofPosition) {
            throw new \OutOfBoundsException('Cannot read bits, it exceeds the boundary of the file');
        }

        $result = 0;
        $bits = $count;

        if ($this->currentBit != 0) {
            $bitsLeft = 8 - $this->currentBit;

            if ($bitsLeft < $bits) {
                $bits -= $bitsLeft;
                $result = ($this->currentByte >> $this->currentBit) << $bits;
            } elseif ($bitsLeft > $bits) {
                $this->currentBit += $bits;

                return ($this->currentByte >> $this->currentBit) & $this->loMasks[$bits];
            } else {
                $this->currentBit = 0;

                return $this->currentByte >> $this->currentBit;
            }
        }

        if ($bits >= 8) {
            $bytes = (int) $bits / 8;

            if ($bytes == 1) {
                $bits -= 8;
                $result |= $this->readUInt8() << $bits;
            } elseif ($bytes == 2) {
                $bits -= 16;
                $result |= $this->readUInt16() << $bits;
            } elseif ($bytes == 4) {
                $bits -= 32;
                $result |= $this->readUInt32() << $bits;
            } else {
                while ($bits > 8) {
                    $bits -= 8;
                    $result |= $this->readUInt8() << 8;
                }
            }
        }

        if ($bits != 0) {
            $this->currentByte = $this->readUInt8();
            $result |= $this->currentByte & $this->loMasks[$bits];
        }

        $this->currentBit = $bits;

        return $result;
    }

    /**
     * @return int
     * @throws \OutOfBoundsException
     */
    public function readUInt8()
    {
        if (($this->position + 1) > $this->eofPosition) {
            throw new \OutOfBoundsException('Cannot read byte, it exceeds the boundary of the file');
        }

        $data = unpack("C", substr($this->str, $this->position, 1));
        $data = $data[1];
        $this->position++;

        if ($this->currentBit != 0) {
            $loMask = $this->bitMasks[$this->currentBit][0];
            $hiMask = $this->bitMasks[$this->currentBit][1];
            $hiBits = $this->currentByte & $hiMask;
            $loBits = $data & $loMask;
            $this->currentByte = $data;
            $data = $hiBits | $loBits;
        }

        return $data;
    }

    /**
     * @param  int   $count
     * @return mixed
     */
    public function readBytes($count)
    {
        return $this->readBits(8 * $count);
    }

    /**
     * @return int
     * @throws \OutOfBoundsException
     */
    public function readUInt16()
    {
        if (($this->position + 2) > $this->eofPosition) {
            throw new \OutOfBoundsException('Cannot read 16-bit int, it exceeds the boundary of the file');
        }

        $endian = $this->endian == 'big' ? 'n' : 'v';
        $data = unpack($endian, substr($this->str, $this->position, 2));
        $data = $data[1];
        $this->position += 2;

        if ($this->currentBit != 0) {
            $loMask = $this->bitMasks[$this->currentBit][0];
            $hiMask = $this->bitMasks[$this->currentBit][1];
            $hiBits = ($this->currentByte & $hiMask) << 8;
            $miBits = ($data & 0xFF00) >> (8 - $this->currentBit);
            $loBits = ($data & $loMask);
            $this->currentByte = $data & 0xFF;
            $data = $hiBits | $miBits | $loBits;
        }

        return $data;
    }

    /**
     * @return int
     * @throws \OutOfBoundsException
     */
    public function readUInt32()
    {
        if (($this->position + 4) > $this->eofPosition) {
            throw new \OutOfBoundsException('Cannot read 32-bit int, it exceeds the boundary of the file');
        }

        $endian = $this->endian == 'big' ? 'N' : 'V';
        $data = unpack($endian, substr($this->str, $this->position, 4));
        $data = $data[1];
        $this->position += 4;

        if ($this->currentBit != 0) {
            $loMask = $this->bitMasks[$this->currentBit][0];
            $hiMask = $this->bitMasks[$this->currentBit][1];
            $hiBits = ($this->currentByte & $hiMask) << 24;
            $miBits = ($data & 0xFFFFFF00) >> (8 - $this->currentBit);
            $loBits = ($data & $loMask);
            $this->currentByte = $data & 0xFF;
            $data = $hiBits | $miBits | $loBits;
        }

        return $data;
    }

    public function readString($len)
    {
        if (($len + $this->position) > $this->eofPosition) {
            throw new \OutOfBoundsException('Cannot read string, it exceeds the boundary of the file');
        }

        $str = substr($this->str, $this->position, (int) $len);
        $this->position += $len;

        return $str;
    }
}