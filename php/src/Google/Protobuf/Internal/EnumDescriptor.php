<?php

namespace Google\Protobuf\Internal;

use Google\Protobuf\EnumValueDescriptor;
use Google\Protobuf\Internal\CodedInputStream;
use Google\Protobuf\Internal\GPBWire;

class EnumDescriptor
{
    use HasPublicDescriptorTrait;

    private $klass;
    private $legacy_klass;
    private $full_name;
    private $value;
    private $name_to_value;
    private $custom_name_to_value = [];
    private $value_descriptor = [];

    public function __construct()
    {
        $this->public_desc = new \Google\Protobuf\EnumDescriptor($this);
    }

    public function setFullName($full_name)
    {
        $this->full_name = $full_name;
    }

    public function getFullName()
    {
        return $this->full_name;
    }

    public function addValue($number, $value, $custom_json_name = null)
    {
        if (!($value instanceof EnumValueDescriptor)) {
            $value = new EnumValueDescriptor($value->getName(), $number, $custom_json_name);
        }
        $this->value[$number] = $value;
        $this->name_to_value[$value->getName()] = $value;
        $this->value_descriptor[] = $value;
        if ($custom_json_name !== null) {
            $this->custom_name_to_value[$custom_json_name] = $value;
        }
    }

    private static function extractCustomJsonName($options)
    {
        if ($options === null) {
            return null;
        }
        $unknown = $options->getUnknown();
        if ($unknown === null || $unknown === "") {
            return null;
        }
        $input = new CodedInputStream($unknown);
        while (($tag = $input->readTag()) !== 0) {
            $number = GPBWire::getTagFieldNumber($tag);
            $wire_type = GPBWire::getTagWireType($tag);
            if ($number === 998 && $wire_type === GPBWire::WIRETYPE_LENGTH_DELIMITED) {
                $length = 0;
                if ($input->readVarint32($length)) {
                    $msg_data = null;
                    if ($input->readRaw($length, $msg_data) && $msg_data !== null) {
                        $inner_input = new CodedInputStream($msg_data);
                        while (($inner_tag = $inner_input->readTag()) !== 0) {
                            $inner_number = GPBWire::getTagFieldNumber($inner_tag);
                            $inner_wire = GPBWire::getTagWireType($inner_tag);
                            if ($inner_number === 1 && $inner_wire === GPBWire::WIRETYPE_LENGTH_DELIMITED) {
                                $str_length = 0;
                                if ($inner_input->readVarint32($str_length)) {
                                    $str_data = null;
                                    if ($inner_input->readRaw($str_length, $str_data)) {
                                        return $str_data;
                                    }
                                }
                            } else {
                                self::skipUnknownField($inner_input, $inner_tag);
                            }
                        }
                    }
                }
            } else {
                self::skipUnknownField($input, $tag);
            }
        }
        return null;
    }

    private static function skipUnknownField($input, $tag)
    {
        $wire_type = GPBWire::getTagWireType($tag);
        switch ($wire_type) {
            case GPBWire::WIRETYPE_VARINT:
                $tmp = 0;
                $input->readVarint64($tmp);
                break;
            case GPBWire::WIRETYPE_FIXED64:
                $tmp = 0;
                $input->readLittleEndian64($tmp);
                break;
            case GPBWire::WIRETYPE_LENGTH_DELIMITED:
                $length = 0;
                if ($input->readVarint32($length)) {
                    $tmp = null;
                    $input->readRaw($length, $tmp);
                }
                break;
            case GPBWire::WIRETYPE_FIXED32:
                $tmp = 0;
                $input->readLittleEndian32($tmp);
                break;
            default:
                break;
        }
    }

    public function getValueByNumber($number)
    {
        if (isset($this->value[$number])) {
            return $this->value[$number];
        }
        return null;
    }

    public function getValueByName($name)
    {
        if (isset($this->name_to_value[$name])) {
            return $this->name_to_value[$name];
        }
        return null;
    }

    public function getValueByCustomJsonName($name)
    {
        if (isset($this->custom_name_to_value[$name])) {
            return $this->custom_name_to_value[$name];
        }
        return null;
    }

    public function getValueDescriptorByIndex($index)
    {
        if (isset($this->value_descriptor[$index])) {
            return $this->value_descriptor[$index];
        }
        return null;
    }

    public function getValueCount()
    {
        return count($this->value);
    }

    public function setClass($klass)
    {
        $this->klass = $klass;
    }

    public function getClass()
    {
        return $this->klass;
    }

    public function setLegacyClass($klass)
    {
        $this->legacy_klass = $klass;
    }

    public function getLegacyClass()
    {
        return $this->legacy_klass;
    }

    public static function buildFromProto($proto, $file_proto, $containing)
    {
        $desc = new EnumDescriptor();

        $enum_name_without_package  = "";
        $classname = "";
        $legacy_classname = "";
        $fullname = "";
        GPBUtil::getFullClassName(
            $proto,
            $containing,
            $file_proto,
            $enum_name_without_package,
            $classname,
            $legacy_classname,
            $fullname,
            $unused_previous_classname);
        $desc->setFullName($fullname);
        $desc->setClass($classname);
        $desc->setLegacyClass($legacy_classname);
        $values = $proto->getValue();
        foreach ($values as $value) {
            $custom_json_name = self::extractCustomJsonName($value->getOptions());
            $desc->addValue($value->getNumber(), $value, $custom_json_name);
        }

        return $desc;
    }
}
