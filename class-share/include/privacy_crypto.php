<?php

if (
    !defined(
        'CLASS_SHARE_PRIVACY_KEY_PATH'
    )
) {
    define(
        'CLASS_SHARE_PRIVACY_KEY_PATH',
        '/etc/1024-class-share/master.key'
    );
}


function class_share_privacy_keys()
{
    static $keys = null;

    if ($keys !== null) {
        return $keys;
    }

    if (
        !function_exists(
            'sodium_crypto_secretbox'
        )
    ) {
        throw new RuntimeException(
            'Sodium 암호화 기능을 사용할 수 없습니다.'
        );
    }

    if (
        !is_readable(
            CLASS_SHARE_PRIVACY_KEY_PATH
        )
    ) {
        throw new RuntimeException(
            '개인정보 암호화 키를 읽을 수 없습니다.'
        );
    }

    $encoded_key =
        file_get_contents(
            CLASS_SHARE_PRIVACY_KEY_PATH
        );

    if ($encoded_key === false) {
        throw new RuntimeException(
            '개인정보 암호화 키를 불러올 수 없습니다.'
        );
    }

    $encoded_key =
        trim(
            $encoded_key
        );

    try {
        $master_key =
            sodium_base642bin(
                $encoded_key,
                SODIUM_BASE64_VARIANT_ORIGINAL
            );
    } catch (Throwable $e) {
        throw new RuntimeException(
            '개인정보 암호화 키 형식이 올바르지 않습니다.'
        );
    }

    if (
        strlen($master_key) !==
        SODIUM_CRYPTO_SECRETBOX_KEYBYTES
    ) {
        sodium_memzero(
            $master_key
        );

        throw new RuntimeException(
            '개인정보 암호화 키 길이가 올바르지 않습니다.'
        );
    }

    $encryption_key =
        sodium_crypto_generichash(
            'class-share-phone-encryption-v1',
            $master_key,
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES
        );

    $lookup_key =
        sodium_crypto_generichash(
            'class-share-phone-lookup-v1',
            $master_key,
            32
        );

    sodium_memzero(
        $master_key
    );

    $keys = array(
        'encryption' =>
            $encryption_key,

        'lookup' =>
            $lookup_key
    );

    return $keys;
}


function class_share_normalize_phone(
    $phone
) {
    $normalized =
        preg_replace(
            '/[^0-9]/',
            '',
            trim(
                (string)$phone
            )
        );

    if (
        $normalized === null ||
        !preg_match(
            '/^[0-9]{10,11}$/',
            $normalized
        )
    ) {
        throw new InvalidArgumentException(
            '연락처 형식이 올바르지 않습니다.'
        );
    }

    return $normalized;
}


function class_share_encrypt_phone(
    $phone
) {
    $normalized =
        class_share_normalize_phone(
            $phone
        );

    $keys =
        class_share_privacy_keys();

    $nonce =
        random_bytes(
            SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        );

    $ciphertext =
        sodium_crypto_secretbox(
            $normalized,
            $nonce,
            $keys['encryption']
        );

    $encoded =
        sodium_bin2base64(
            $nonce . $ciphertext,
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
        );

    return
        'v1:' .
        $encoded;
}


function class_share_decrypt_phone(
    $stored_value
) {
    $stored_value =
        (string)$stored_value;

    if (
        strpos(
            $stored_value,
            'v1:'
        ) !== 0
    ) {
        throw new RuntimeException(
            '지원하지 않는 개인정보 암호화 형식입니다.'
        );
    }

    $encoded =
        substr(
            $stored_value,
            3
        );

    try {
        $packed =
            sodium_base642bin(
                $encoded,
                SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
            );
    } catch (Throwable $e) {
        throw new RuntimeException(
            '암호화된 연락처 형식이 올바르지 않습니다.'
        );
    }

    $minimum_length =
        SODIUM_CRYPTO_SECRETBOX_NONCEBYTES +
        SODIUM_CRYPTO_SECRETBOX_MACBYTES;

    if (
        strlen($packed) <=
        $minimum_length
    ) {
        throw new RuntimeException(
            '암호화된 연락처 길이가 올바르지 않습니다.'
        );
    }

    $nonce =
        substr(
            $packed,
            0,
            SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        );

    $ciphertext =
        substr(
            $packed,
            SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        );

    $keys =
        class_share_privacy_keys();

    $plaintext =
        sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $keys['encryption']
        );

    if ($plaintext === false) {
        throw new RuntimeException(
            '연락처를 복호화할 수 없습니다.'
        );
    }

    return
        class_share_normalize_phone(
            $plaintext
        );
}


function class_share_phone_lookup_hash(
    $phone
) {
    $normalized =
        class_share_normalize_phone(
            $phone
        );

    $keys =
        class_share_privacy_keys();

    return hash_hmac(
        'sha256',
        $normalized,
        $keys['lookup']
    );
}


function class_share_phone_last4(
    $phone
) {
    $normalized =
        class_share_normalize_phone(
            $phone
        );

    return substr(
        $normalized,
        -4
    );
}


function class_share_mask_phone(
    $phone
) {
    return
        '***-****-' .
        class_share_phone_last4(
            $phone
        );
}
