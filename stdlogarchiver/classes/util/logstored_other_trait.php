<?php namespace tool_stdlogarchiver\util;

trait logstored_other_trait{

    public static function is_serialized($string) : bool {
        return (@unserialize($string) !== false);
    }

    public static function is_json($string) : bool {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * It encodes serialized data as json
     *
     * @see \logstore_standard\log\store::decode_other()
     * @param string $string JSON or SERIALIZED data
     * @return string
     */
    public static function to_json(?string $other) : string {
        if ($other === 'N;' || preg_match('~^.:~', $other ?? '')) {
            return json_encode(@unserialize($other));
        } else {
            return $other;
        }
    }

    /**
     * It serializes json encoded data
     *
     * @see \logstore_standard\log\store::decode_other()
     * @param string $string JSON or SERIALIZED data
     * @return string
     */
    public static function to_serialized($other) : string {
        if ($other === 'N;' || preg_match('~^.:~', $other ?? '')) {
            return $other;
        } else {
            return serialize(json_decode($other));;
        }
    }
    
}