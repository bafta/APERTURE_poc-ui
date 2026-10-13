<?php
/**
 * Aperture PoC — Live demo of Paillier homomorphic encryption.
 *
 * Run:  php scripts/demo.php
 *
 * Walks through encryption, randomisation, and homomorphic addition
 * step by step with coloured terminal output — designed to be run
 * during a presentation.
 */

require_once(__DIR__.'/../lib/testKeys.php');

$PUB  = MASTER_PAL_ENC_KEY;
$PRIV = MASTER_PAL_DEC_KEY;

// ── helpers ──────────────────────────────────────────────────────
function bold(string $s): string  { return "\033[1m$s\033[0m"; }
function dim(string $s): string   { return "\033[2m$s\033[0m"; }
function green(string $s): string { return "\033[32m$s\033[0m"; }
function cyan(string $s): string  { return "\033[36m$s\033[0m"; }
function yellow(string $s): string { return "\033[33m$s\033[0m"; }
function red(string $s): string   { return "\033[31m$s\033[0m"; }

function heading(string $s): void {
    echo "\n" . bold("══ $s ══") . "\n\n";
}

function pause(): void {
    echo dim("\n  ▸ Press Enter to continue…");
    fgets(STDIN);
    echo "\n";
}

function truncCipher(string $c, int $len = 40): string {
    if (strlen($c) <= $len) return $c;
    return substr($c, 0, $len) . dim('…(' . strlen($c) . ' chars)');
}

// ── 1. Basic encryption ─────────────────────────────────────────
heading('1 · Encrypting a simple value');

echo "  Let's encrypt the number " . bold('1') . " (a \"Yes\" answer).\n";
pause();

$enc = pal_encrypt_array($PUB, [1]);
$c1  = $enc[0];

echo "  Plaintext:   " . green('1') . "\n";
echo "  Ciphertext:  " . cyan(truncCipher($c1)) . "\n";
echo "\n  The ciphertext is a " . bold(strlen($c1) . "-character") . " base-36 number.\n";
echo "  There is " . bold('no way') . " to recover the original 1 without the private key.\n";
pause();

// ── 2. Randomisation ────────────────────────────────────────────
heading('2 · Same plaintext, different ciphertext');

echo "  Paillier encryption is " . bold('randomised') . ".\n";
echo "  Let's encrypt " . bold('1') . " again:\n";
pause();

$enc2 = pal_encrypt_array($PUB, [1]);
$c2   = $enc2[0];

echo "  First time:   " . cyan(truncCipher($c1)) . "\n";
echo "  Second time:  " . cyan(truncCipher($c2)) . "\n";
echo "\n  " . ($c1 === $c2 ? red('Identical!') : green('Completely different!')) . "\n";
echo "  Even with access to the database, you cannot search for a\n";
echo "  known answer — every encryption is unique.\n";
pause();

// ── 3. Homomorphic addition ─────────────────────────────────────
heading('3 · Adding encrypted values WITHOUT decrypting');

echo "  This is the core trick.\n\n";
echo "  Imagine 5 people answer a Yes/No question.\n";
echo "  Three say Yes (1), two say No (0).\n";
pause();

$votes     = [1, 1, 0, 1, 0];
$encVotes  = pal_encrypt_array($PUB, $votes);

echo "  Plaintext votes:  " . implode(', ', array_map(fn($v) => $v ? green('1') : red('0'), $votes)) . "\n\n";
echo "  Encrypted votes (each is ~" . strlen($encVotes[0]) . " chars):\n";
foreach ($encVotes as $i => $ev) {
    $label = $votes[$i] ? green('Yes') : red('No ');
    echo "    Vote " . ($i + 1) . " ($label):  " . cyan(truncCipher($ev, 30)) . "\n";
}
pause();

echo "  Now the server multiplies these ciphertexts together.\n";
echo "  It " . bold('never decrypts') . " any individual vote.\n";
pause();

$encSum = pal_add_array($PUB, $encVotes);

echo "  Encrypted sum:  " . cyan(truncCipher($encSum, 30)) . "\n";
pause();

echo "  Only the Key Guardian can decrypt the sum:\n\n";

$decSum = gmp_intval(gmp_init(pal_decrypt($PRIV, $encSum), 36));

echo "  Decrypted sum:  " . bold(green((string)$decSum)) . "\n\n";
echo "  3 out of 5 people said Yes — and we learned " . bold('nothing') . "\n";
echo "  about any individual's answer.\n";
pause();

// ── 4. Multi-option question ────────────────────────────────────
heading('4 · A realistic question with multiple options');

$options = ['Cats', 'Dogs', 'Fish'];
echo "  Question: \"Which pet do you prefer?\"\n";
echo "  Options:  " . implode(', ', $options) . "\n\n";

// Simulate 5 respondents (kept small — Paillier encryption is deliberately
// expensive, and we don't want the audience waiting)
$nRespondents = 5;
$responses = [];
$counts = array_fill(0, count($options), 0);
for ($i = 0; $i < $nRespondents; $i++) {
    $pick = random_int(0, count($options) - 1);
    $row  = array_fill(0, count($options), 0);
    $row[$pick] = 1;
    $responses[] = $row;
    $counts[$pick]++;
}

echo "  Simulating " . bold((string)$nRespondents) . " respondents…\n";
$total = $nRespondents * count($options);
echo "  Encrypting $total individual answers";

// Encrypt each response and homomorphically sum per-option
$encSums = [];
for ($o = 0; $o < count($options); $o++) {
    $optionVotes = array_column($responses, $o);
    $encOpts     = pal_encrypt_array($PUB, $optionVotes);
    $encSums[$o] = pal_add_array($PUB, $encOpts);
    echo '.';
}

echo " done.\n\n";
echo "  All {$nRespondents}×" . count($options) . " = $total individual ciphertexts summed into " . count($options) . " encrypted totals.\n";
echo "  The server still has " . bold('no idea') . " who picked what.\n";
pause();

echo "  Key Guardian decrypts the " . count($options) . " totals:\n\n";

$maxCount = max($counts);
for ($o = 0; $o < count($options); $o++) {
    $dec  = gmp_intval(gmp_init(pal_decrypt($PRIV, $encSums[$o]), 36));
    $bar  = str_repeat('█', (int)round($dec / max($maxCount, 1) * 20));
    $pad  = str_pad($options[$o], 6);
    echo "    $pad " . green(str_pad((string)$dec, 3, ' ', STR_PAD_LEFT)) . "  $bar\n";
}

echo "\n  These totals are the " . bold('only') . " information that ever gets decrypted.\n";
echo "  Individual choices remain encrypted forever.\n";
pause();

// ── 5. The attacker's view ──────────────────────────────────────
heading('5 · What an attacker sees');

echo "  Suppose an attacker compromises the Data Server and dumps the database.\n";
echo "  Here is what they find for one respondent's encrypted answer:\n\n";

echo "    " . cyan(wordwrap($encVotes[0], 76, "\n    ", true)) . "\n\n";

echo "  No amount of computation can tell them whether this is Yes or No.\n";
echo "  Paillier is " . bold('semantically secure') . " — the ciphertext reveals\n";
echo "  absolutely nothing about the plaintext.\n";
pause();

// ── done ─────────────────────────────────────────────────────────
heading('Summary');

echo "  ✓  Individual answers encrypted client-side, never decrypted on server\n";
echo "  ✓  Same answer encrypts differently every time\n";
echo "  ✓  Server sums encrypted values without decrypting\n";
echo "  ✓  Only aggregate totals are ever decrypted\n";
echo "  ✓  Compromising the database reveals nothing about individuals\n\n";
