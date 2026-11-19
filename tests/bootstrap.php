<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// A throwaway key pair for the test environment, so no key is ever committed.
(static function (): void {
    $dir = dirname(__DIR__).'/var/test-jwt';
    if (is_file($dir.'/private.pem') && is_file($dir.'/public.pem')) {
        return;
    }

    $passphrase = $_SERVER['JWT_PASSPHRASE'];
    if (!is_string($passphrase) || '' === $passphrase) {
        throw new RuntimeException('JWT_PASSPHRASE must be set in .env.test.');
    }

    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $details = false !== $key ? openssl_pkey_get_details($key) : false;
    if (false === $key || false === $details || !openssl_pkey_export($key, $privatePem, $passphrase)) {
        throw new RuntimeException('Could not generate the test JWT key pair: '.openssl_error_string());
    }

    if (!is_dir($dir) && !mkdir($dir, 0o700, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create '.$dir);
    }
    file_put_contents($dir.'/private.pem', $privatePem);
    file_put_contents($dir.'/public.pem', $details['key']);
})();
