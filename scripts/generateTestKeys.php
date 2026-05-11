<?php
/**
 * Generates lib/testKeys.php containing:
 *  - A master Paillier keypair (MASTER_PAL_ENC_KEY, MASTER_PAL_DEC_KEY)
 *  - A test user Paillier public key (USER_PAL_ENC_KEY)
 *  - A master libsodium signing keypair, with the private key encrypted at rest
 *    (MASTER_SODIUM_KEYS, passphrase, salt constants)
 *
 * Run from the project root:
 *   php scripts/generateTestKeys.php
 */

if (!function_exists('pal_generate_keys')) {
    die("ERROR: pal_generate_keys() not available — is the Paillier PHP extension loaded?\n");
}

echo "Generating master Paillier keypair...\n";
$masterPalKeys = pal_generate_keys();

echo "Generating user Paillier keypair...\n";
$userPalKeys = pal_generate_keys();

echo "Generating sodium signing keypair...\n";
$sodiumSignKeypair = sodium_crypto_sign_keypair();
$sodiumPublicKey   = sodium_crypto_sign_publickey($sodiumSignKeypair);
$sodiumPrivateKey  = sodium_crypto_sign_secretkey($sodiumSignKeypair);

// Derive a key-encryption-key from a random passphrase + salt, then encrypt the private key at rest.
$passphrase = bin2hex(random_bytes(16)); // readable ASCII constant
$salt       = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
$kek        = sodium_crypto_pwhash(
    SODIUM_CRYPTO_BOX_SECRETKEYBYTES,
    $passphrase,
    $salt,
    SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
    SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    SODIUM_CRYPTO_PWHASH_ALG_DEFAULT
);
$nonce         = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$encPrivateKey = sodium_crypto_secretbox($sodiumPrivateKey, $nonce, $kek);

// Helper: emit a define() storing binary data via hex2bin()
function hexDefine(string $name, string $binaryValue): string {
    return "define('" . $name . "', hex2bin('" . bin2hex($binaryValue) . "'));\n";
}

$output  = "<?php\n";
$output .= "// Auto-generated test keys — DO NOT use in production.\n";
$output .= "// Regenerate with: php scripts/generateTestKeys.php\n\n";

$output .= "// Master Paillier keypair\n";
$output .= hexDefine('MASTER_PAL_ENC_KEY', $masterPalKeys['encryption_key']);
$output .= hexDefine('MASTER_PAL_DEC_KEY', $masterPalKeys['decryption_key']);
$output .= "\n";

$output .= "// Test user Paillier public key\n";
$output .= hexDefine('USER_PAL_ENC_KEY', $userPalKeys['encryption_key']);
$output .= "\n";

$output .= "// Master sodium signing keypair (private key encrypted at rest)\n";
$output .= "define('passphrase', '" . $passphrase . "');\n";
$output .= hexDefine('salt', $salt);
$output .= "define('MASTER_SODIUM_KEYS', [\n";
$output .= "    'publicKey'    => hex2bin('" . bin2hex($sodiumPublicKey) . "'),\n";
$output .= "    'encPrivateKey'=> hex2bin('" . bin2hex($encPrivateKey)  . "'),\n";
$output .= "    'nonce'        => hex2bin('" . bin2hex($nonce)          . "'),\n";
$output .= "]);\n";

$outputPath = __DIR__ . '/../lib/testKeys.php';
file_put_contents($outputPath, $output);

echo "Written to $outputPath\n";
echo "Done.\n";
