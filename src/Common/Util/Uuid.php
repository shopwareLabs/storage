<?php

namespace Shopware\Storage\Common\Util;

use Ramsey\Uuid\BinaryUtils;
use Ramsey\Uuid\Generator\RandomGeneratorFactory;
use Ramsey\Uuid\Generator\UnixTimeGenerator;

class Uuid
{
    /**
     * Regular expression pattern for matching a valid UUID of any variant.
     */
    final public const VALID_PATTERN = '^[0-9a-f]{32}$';

    private static ?UnixTimeGenerator $generator = null;

    public static function randomHex(): string
    {
        return bin2hex(self::randomBytes());
    }

    public static function randomBytes(): string
    {
        if (self::$generator === null) {
            self::$generator = new UnixTimeGenerator((new RandomGeneratorFactory())->getGenerator());
        }
        $bytes = self::$generator->generate();

        /** @var array<int> $unpackedTime */
        $unpackedTime = unpack('n*', substr($bytes, 6, 2));
        $timeHi = (int) $unpackedTime[1];
        $timeHiAndVersion = pack('n*', BinaryUtils::applyVersion($timeHi, 7));

        /** @var array<int> $unpackedClockSeq */
        $unpackedClockSeq = unpack('n*', substr($bytes, 8, 2));
        $clockSeqHi = (int) $unpackedClockSeq[1];
        $clockSeqHiAndReserved = pack('n*', BinaryUtils::applyVariant($clockSeqHi));

        $bytes = substr_replace($bytes, $timeHiAndVersion, 6, 2);

        return substr_replace($bytes, $clockSeqHiAndReserved, 8, 2);
    }

    public static function fromBytesToHex(string $bytes): string
    {
        if (mb_strlen($bytes, '8bit') !== 16) {
            throw new \LogicException(sprintf(
                'UUID has a invalid length. 16 bytes expected, %s given. Hexadecimal reprensentation: %s',
                mb_strlen($bytes, '8bit'),
                bin2hex($bytes)
            ));
        }
        $uuid = bin2hex($bytes);

        if (!self::isValid($uuid)) {
            throw new \LogicException('Invalid uuid exception ' . $uuid);
        }

        return $uuid;
    }

    /**
     * @param array<string> $bytes
     * @return array<string>
     */
    public static function fromBytesToHexList(array $bytes): array
    {
        $converted = [];
        foreach ($bytes as $key => $value) {
            $converted[$key] = self::fromBytesToHex($value);
        }

        return $converted;
    }

    /**
     * @param array<string> $uuids
     * @return array<string>
     */
    public static function fromHexToBytesList(array $uuids): array
    {
        $converted = [];
        foreach ($uuids as $key => $uuid) {
            $converted[$key] = self::fromHexToBytes($uuid);
        }

        return $converted;
    }

    public static function fromHexToBytes(string $uuid): string
    {
        if ($bin = @hex2bin($uuid)) {
            return $bin;
        }

        throw new \LogicException('Invalid uuid exception');
    }

    /**
     * Generates a md5 binary, to hash the string and returns a UUID in hex
     */
    public static function fromStringToHex(string $string): string
    {
        return self::fromBytesToHex(md5($string, true));
    }

    public static function isValid(string $id): bool
    {
        if (!preg_match('/' . self::VALID_PATTERN . '/', $id)) {
            return false;
        }

        return true;
    }
}
