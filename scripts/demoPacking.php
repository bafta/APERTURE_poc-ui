<?php
/**
 * Aperture PoC — Demo of the answer-packing bitfield scheme.
 *
 * Run:  php scripts/demoPacking.php
 *
 * Shows how individual 0/1 answers are packed into a single number
 * using 16-bit slots, how multiplying by powers of 2 shifts them
 * into position, and how summing packed values across respondents
 * produces a frequency distribution — all without Paillier.
 *
 * The point: these same multiply-and-add operations work identically
 * on encrypted values because Paillier is homomorphic.
 */

define('BITS_PER_ANSWER', 16);
define('ANSWER_MASK', (1 << BITS_PER_ANSWER) - 1);  // 0xFFFF

// ── helpers ──────────────────────────────────────────────────────
function bold(string $s): string   { return "\033[1m$s\033[0m"; }
function dim(string $s): string    { return "\033[2m$s\033[0m"; }
function green(string $s): string  { return "\033[32m$s\033[0m"; }
function cyan(string $s): string   { return "\033[36m$s\033[0m"; }
function yellow(string $s): string { return "\033[33m$s\033[0m"; }
function red(string $s): string    { return "\033[31m$s\033[0m"; }
function mag(string $s): string    { return "\033[35m$s\033[0m"; }

function heading(string $s): void {
    echo "\n" . bold("══ $s ══") . "\n\n";
}

function pause(): void {
    echo dim("\n  ▸ Press Enter to continue…");
    fgets(STDIN);
    echo "\n";
}

/** Format a GMP integer as coloured 16-bit groups in binary */
function binarySlots($value, int $numSlots): string {
    $colours = ["\033[32m", "\033[36m", "\033[33m", "\033[35m", "\033[34m"];
    $reset   = "\033[0m";

    $parts = [];
    $v = gmp_init($value);
    for ($i = 0; $i < $numSlots; $i++) {
        $chunk = gmp_intval($v & ANSWER_MASK);
        $bin   = str_pad(decbin($chunk), BITS_PER_ANSWER, '0', STR_PAD_LEFT);
        $col   = $colours[$i % count($colours)];
        $parts[] = $col . $bin . $reset;
        $v >>= BITS_PER_ANSWER;
    }

    return implode(' ', array_reverse($parts));
}

/** Format a GMP integer showing the decimal value of each 16-bit slot */
function decimalSlots($value, int $numSlots): string {
    $colours = ["\033[32m", "\033[36m", "\033[33m", "\033[35m", "\033[34m"];
    $reset   = "\033[0m";

    $parts = [];
    $v = gmp_init($value);
    for ($i = 0; $i < $numSlots; $i++) {
        $chunk = gmp_intval($v & ANSWER_MASK);
        $col   = $colours[$i % count($colours)];
        $parts[] = $col . str_pad($chunk, 5, ' ', STR_PAD_LEFT) . $reset;
        $v >>= BITS_PER_ANSWER;
    }

    return implode('  ', array_reverse($parts));
}

$options    = ['Cats', 'Dogs', 'Fish', 'Birds'];
$numOptions = count($options);
$colNames   = ["\033[32m", "\033[36m", "\033[33m", "\033[35m"];
$reset      = "\033[0m";

// ── 1. The problem ──────────────────────────────────────────────
heading('1 · One question, multiple options');

echo "  Question: " . bold('"Which pet do you prefer?"') . "\n";
echo "  Options:  ";
for ($i = 0; $i < $numOptions; $i++) {
    echo $colNames[$i] . $options[$i] . $reset . ($i < $numOptions - 1 ? ', ' : '');
}
echo "\n\n";
echo "  A respondent picks " . bold('Dogs') . ". Their raw answers are:\n\n";

$pick = [0, 1, 0, 0]; // Dogs
echo "            ";
for ($i = 0; $i < $numOptions; $i++) {
    echo $colNames[$i] . str_pad($options[$i], 7) . $reset;
}
echo "\n            ";
for ($i = 0; $i < $numOptions; $i++) {
    echo $colNames[$i] . str_pad($pick[$i], 7) . $reset;
}
echo "\n\n";
echo "  Each 0 or 1 is encrypted separately and sent to the server.\n";
echo "  But we want to store all four as " . bold('one') . " number.\n";
pause();

// ── 2. The 16-bit slot idea ─────────────────────────────────────
heading('2 · Reserving 16 bits per option');

echo "  We give each option its own 16-bit slot in a big number:\n\n";
echo "    " . dim('bit position:') . "  ";
echo dim('63') . "              " . dim('48  47') . "              " . dim('32  31') . "              " . dim('16  15') . "               " . dim('0') . "\n";
echo "                ┌────────────────┬────────────────┬────────────────┬────────────────┐\n";
echo "                │";
for ($i = $numOptions - 1; $i >= 0; $i--) {
    echo $colNames[$i] . '    ' . str_pad($options[$i], 8) . '    ' . $reset . '│';
}
echo "\n";
echo "                │";
for ($i = $numOptions - 1; $i >= 0; $i--) {
    echo $colNames[$i] . '    16 bits     ' . $reset . '│';
}
echo "\n";
echo "                └────────────────┴────────────────┴────────────────┴────────────────┘\n\n";

echo "  16 bits per slot means each option can count up to " . bold('65,535') . " respondents.\n";
echo "  A 2048-bit Paillier key can hold " . bold('128') . " options in one ciphertext.\n";
pause();

// ── 3. Shifting into position ───────────────────────────────────
heading('3 · Shifting answers into their slots');

echo "  To place an answer in the right slot, multiply by " . bold('2^(slot × 16)') . ":\n\n";

$shifted = [];
for ($i = 0; $i < $numOptions; $i++) {
    $shift  = gmp_init($pick[$i]) * gmp_pow(2, $i * BITS_PER_ANSWER);
    $shifted[] = $shift;

    $label = $colNames[$i] . str_pad($options[$i], 6) . $reset;
    $val   = $pick[$i];
    $mult  = '2^' . ($i * BITS_PER_ANSWER);

    if ($val == 0) {
        echo "    $label  answer=$val  × $mult  =  " . dim('0 (nothing to shift)') . "\n";
    } else {
        echo "    $label  answer=$val  × $mult  =  $val × " . bold(gmp_strval(gmp_pow(2, $i * BITS_PER_ANSWER))) . "\n";
    }
    echo "           " . binarySlots($shift, $numOptions) . "\n\n";
}

pause();

// ── 4. Adding the shifted values ────────────────────────────────
heading('4 · Adding the shifted values together');

echo "  Now we just add them. The bits never overlap because each\n";
echo "  answer sits in its own 16-bit lane:\n\n";

for ($i = 0; $i < $numOptions; $i++) {
    $label = $pick[$i] ? '+' : ' ';
    echo "    $label " . binarySlots($shifted[$i], $numOptions) . "\n";
}

$packed = gmp_init(0);
foreach ($shifted as $s) $packed = gmp_add($packed, $s);

echo "    " . bold('─────────────────────────────────────────────────────────────────────') . "\n";
echo "    = " . binarySlots($packed, $numOptions) . "\n\n";
echo "  One number: " . bold(gmp_strval($packed)) . "\n";
echo "  This is what gets stored in the database at " . bold('optionNum=0') . ".\n";
pause();

// ── 5. Why this works with Paillier ─────────────────────────────
heading('5 · Why this works on encrypted data');

echo "  Paillier gives us two operations on ciphertexts:\n\n";
echo "    " . bold('multiply(ciphertext, scalar)') . "  →  encrypts (plaintext × scalar)\n";
echo "    " . bold('add(ciphertext, ciphertext)') . "   →  encrypts (plaintext + plaintext)\n\n";
echo "  Multiply = shift into slot.  Add = combine slots.\n";
echo "  The server does both " . bold('without ever decrypting') . ".\n";
pause();

// ── 6. Summing across respondents ───────────────────────────────
heading('6 · Summing packed values across respondents');

$respondents = [
    'Alice'   => [0, 1, 0, 0],  // Dogs
    'Bob'     => [1, 0, 0, 0],  // Cats
    'Charlie' => [0, 0, 1, 0],  // Fish
    'Diana'   => [0, 1, 0, 0],  // Dogs
    'Eve'     => [1, 0, 0, 0],  // Cats
    'Frank'   => [0, 0, 0, 1],  // Birds
    'Grace'   => [0, 1, 0, 0],  // Dogs
];

echo "  7 respondents, each with a packed answer:\n\n";
echo "    " . dim(str_pad('', 10));
for ($i = $numOptions - 1; $i >= 0; $i--) {
    echo $colNames[$i] . str_pad($options[$i], 18, ' ', STR_PAD_BOTH) . $reset;
}
echo "\n";

$packedValues = [];
foreach ($respondents as $name => $picks) {
    $p = gmp_init(0);
    for ($i = 0; $i < $numOptions; $i++) {
        $p = gmp_add($p, gmp_init($picks[$i]) * gmp_pow(2, $i * BITS_PER_ANSWER));
    }
    $packedValues[] = $p;

    $chosen = $options[array_search(1, $picks)];
    echo '    ' . str_pad($name, 10) . binarySlots($p, $numOptions) . "  ($chosen)\n";
}

pause();

echo "  The server adds all 7 packed numbers (in Paillier: homomorphic sum):\n\n";

$grandTotal = gmp_init(0);
foreach ($packedValues as $pv) {
    $grandTotal = gmp_add($grandTotal, $pv);
}

echo "    Sum: " . binarySlots($grandTotal, $numOptions) . "\n\n";
echo "    As decimal per slot:\n\n";
echo "           ";
for ($i = $numOptions - 1; $i >= 0; $i--) {
    echo $colNames[$i] . str_pad($options[$i], 7, ' ', STR_PAD_BOTH) . $reset . '  ';
}
echo "\n           " . decimalSlots($grandTotal, $numOptions) . "\n";

pause();

// ── 7. Unpacking ────────────────────────────────────────────────
heading('7 · Unpacking the frequency distribution');

echo "  The Key Guardian decrypts the single summed ciphertext and\n";
echo "  extracts each 16-bit slot:\n\n";

echo "    " . bold('packed = ') . gmp_strval($grandTotal) . "\n\n";

$v = $grandTotal;
$maxCount = 0;
$freqs = [];
for ($i = 0; $i < $numOptions; $i++) {
    $count = gmp_intval($v & ANSWER_MASK);
    $freqs[] = $count;
    if ($count > $maxCount) $maxCount = $count;
    $v >>= BITS_PER_ANSWER;
}

for ($i = 0; $i < $numOptions; $i++) {
    $label = $colNames[$i] . str_pad($options[$i], 6) . $reset;
    $expr  = dim("(packed >> " . str_pad($i * BITS_PER_ANSWER, 2) . ") & 0xFFFF");
    echo "    $label  $expr  = " . bold((string)$freqs[$i]) . "\n";
}

pause();

echo "  Result:\n\n";
for ($i = 0; $i < $numOptions; $i++) {
    $label = $colNames[$i] . str_pad($options[$i], 6) . $reset;
    $bar   = str_repeat('█', (int)round($freqs[$i] / max($maxCount, 1) * 20));
    echo "    $label " . bold(str_pad((string)$freqs[$i], 2)) . "  $bar\n";
}

echo "\n  Cats: 2, Dogs: 3, Fish: 1, Birds: 1 — matches the inputs exactly.\n";
pause();

// ── summary ─────────────────────────────────────────────────────
heading('Summary');

echo "  1. Each option answer (0 or 1) gets its own 16-bit lane\n";
echo "  2. Multiply by 2^(slot × 16) to shift it into position\n";
echo "  3. Add the shifted values → one packed number per respondent\n";
echo "  4. Sum packed numbers across all respondents\n";
echo "  5. Extract each 16-bit lane → frequency distribution\n\n";
echo "  Steps 2–4 use only multiply and add — the two operations\n";
echo "  that Paillier supports on encrypted data.\n";
echo "  The server never sees any individual answer.\n\n";
