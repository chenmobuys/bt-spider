<?php

namespace BTSpider\Support;

class Bencode
{
    /**
     * Encode data
     *
     * @param mixed $data
     * @return string
     */
    public static function encode($data)
    {
        if (is_array($data)) {
            if (isset($data[0])) {
                $list = '';
                foreach ($data as $value) {
                    $list .= self::encode($value);
                }
                $encode = 'l' . $list . 'e';
            } else {
                $dict = '';
                foreach ($data as $key => $value) {
                    $dict .= self::encode($key) . self::encode($value);
                }
                $encode = 'd' . $dict . 'e';
            }
        } elseif (is_string($data)) {
            $encode = sprintf('%d:%s', strlen($data), $data);
        } elseif (is_int($data)) {
            $encode = sprintf('i%de', $data);
        } else {
            $encode = null;
        }
        return $encode;
    }

    /**
     * Decode data
     *
     * @param string $data
     * @param integer $pos
     * @return string
     */
    public static function decode($data, &$pos = 0)
    {
        if ($pos >= strlen($data)) {
            return null;
        }

        switch ($data[$pos]) {
            case 'd':
                $pos++;
                $retval = array();
                while ($data[$pos] != 'e') {
                    $key = self::decode($data, $pos);
                    $val = self::decode($data, $pos);
                    if ($key === null || $val === null)
                        break;
                    $retval[$key] = $val;
                }
                $pos++;
                return $retval;

            case 'l':
                $pos++;
                $retval = array();
                while ($data[$pos] != 'e') {
                    $val = self::decode($data, $pos);
                    if ($val === null)
                        break;
                    $retval[] = $val;
                }
                $pos++;
                return $retval;

            case 'i':
                $pos++;
                $digits = strpos($data, 'e', $pos) - $pos;
                $val = round((float)substr($data, $pos, $digits));
                $pos += $digits + 1;
                return $val;

                // case "0": case "1": case "2": case "3": case "4":
                // case "5": case "6": case "7": case "8": case "9":
            default:
                $digits = strpos($data, ':', $pos) - $pos;
                if ($digits < 0 || $digits > 20)
                    return null;
                $len = (int) substr($data, $pos, $digits);
                $pos += $digits + 1;
                $str = substr($data, $pos, $len);
                $pos += $len;
                return (string) $str;
        }
        return null;
    }
}
