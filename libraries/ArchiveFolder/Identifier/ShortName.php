<?php
/**
 * Utility class for dealing with identifiers.
 *
 * @package ArchiveFolder
 */
class ArchiveFolder_Identifier_ShortName extends ArchiveFolder_Identifier_Abstract
{
    /**
     * Return the identifier of a record from a list of unique order / record.
     *
     * @param array $data Array of associative arrays of order and record.
     * @return string Oai identifier.
     */
    protected function _create($data)
    {
        $list = array();

        // Get the order for each resource.
        foreach ($data as $resource) {
            $record = reset($resource);
            if (!isset($record['name'])) {
                throw new ArchiveFolder_BuilderException(__('A name is missing for record %s.', key($data[0])));
            }

            $list[] = $record['name'];
        }

        $name = implode(':', $list);

        // Because OAI identifier should be simple and ascii, a simplified md5
        // sum is returned.
        $id = $this->_getParameter('repository_identifier') . ':' . $this->_shortMd5($name);
        return $id;
    }

    /**
     * Return the short md5 sum of a string.
     *
     * @param string $string
     * @return string A string long of 25 characters.
     */
    protected function _shortMd5($string)
    {
        $string = md5($this->_getParameter('repository_identifier') . ':' . $this->_number . ':' . $string);
        $string = strtolower($string);
        $fromBase = '0123456789abcdef';
        // The oai identifier should be case insensitive and universal.
        $toBase = '0123456789abcdefghijklmnopqrstuvwxyz';
        $short = $this->_convBase($string, $fromBase, $toBase);
        return substr(str_repeat('0', 25) . $short, -25, 25);
    }

    /**
     * Convert an arbitrarily large number from any base to any base.
     *
     * @link https://php.net/manual/en/function.base-convert.php#106546
     *
     * @see https://github.com/Daniel-KM/ArkForOmeka
     *
     * @param integer $number Input number to convert as a string.
     * @param string $fromBaseInput base of the number to convert as a string.
     * @param string $toBaseInput base the number should be converted to as a
     * string.
     * @return string
     */
    protected function _convBase($numberInput, $fromBaseInput, $toBaseInput)
    {
        if ($fromBaseInput == $toBaseInput) {
            return $numberInput;
        }

        $fromBase = str_split($fromBaseInput, 1);
        $toBase = str_split($toBaseInput, 1);
        $number = str_split($numberInput, 1);
        $fromLen = strlen($fromBaseInput);
        $toLen = strlen($toBaseInput);
        $numberLen = strlen($numberInput);
        $retval = '';

        if ($toBaseInput == '0123456789') {
            $retval = 0;
            for ($i = 1; $i <= $numberLen; $i++) {
                $retval = bcadd($retval, bcmul(array_search($number[$i - 1], $fromBase), bcpow($fromLen, $numberLen - $i)));
            }
            return $retval;
        }

        if ($fromBaseInput != '0123456789') {
            $base10 = $this->_convBase($numberInput, $fromBaseInput, '0123456789');
        }
        else {
            $base10 = $numberInput;
        }

        if ($base10 < strlen($toBaseInput)) {
            return $toBase[$base10];
        }

        while ($base10 != '0') {
            $retval = $toBase[bcmod($base10, $toLen)] . $retval;
            $base10 = bcdiv($base10, $toLen, 0);
        }

        return $retval;
    }
}
