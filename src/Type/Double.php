<?php

namespace PhpBinaryReader\Type;

use PhpBinaryReader\BinaryReader;
use PhpBinaryReader\Type\TypeInterface;

class Double implements TypeInterface
{

    /**
     * Returns a 8-bytes double
     *
     * @param \PhpBinaryReader\BinaryReader $br
     * @param null $length
     *
     * @return float
     * @throws \OutOfBoundsException
     */
    public function read(BinaryReader &$br, $length = null)
    {
        if (!$br->canReadBytes(8)) {
            throw new \OutOfBoundsException('Cannot read 8-bytes double, it exceeds the boundary of the file');
        }

        $segment = $br->readFromHandle(8);
        if ($br->getCurrentBit() !== 0) {
            $data = unpack('N', $segment)[1];
            $data = $this->bitReader($br, $data);
            $endian = $br->getMachineByteOrder() === $br->getEndian() ? 'N' : 'V';
            $segment = pack($endian, $data);
        } elseif ($br->getMachineByteOrder() !== $br->getEndian()) {
            $segment = strrev($segment);
        }

        $value = unpack('d', $segment)[1];
        return $value;
    }

    /**
     * @param \PhpBinaryReader\BinaryReader $br
     * @param int $data
     *
     * @return int
     */
    private function bitReader(BinaryReader $br, $data)
    {
        $mask = 0x7FFFFFFFFFFFFFFF >> ($br->getCurrentBit() - 1);
        $value = (($data >> (8 - $br->getCurrentBit())) & $mask) | ($br->getNextByte() << (56 + $br->getCurrentBit()));
        $br->setNextByte($data & 0xFF);
        return $value;
    }
}