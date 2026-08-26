# Security Review — Aperture POC

**Date:** 2026-08-26

## Scope and framing

This review answers a specific question: **within the limited functionality the POC actually implements, is the code secure in and of itself?** It is *not* a list of the production features the POC deliberately omits.

The POC's documented, intentional simplifications — disabled user-token HMAC verification, disabled token expiry, disabled question-ID signature verification, unimplemented request/key signing, and master keys loaded from a test file — are treated as known boundaries, **not** findings. Where one of those disabled controls interacts with a genuine implementation defect, that interaction is called out, but "the POC doesn't authenticate users" is never itself reported as a bug.

**In scope:** all first-party code — `lib/aperture*.php`, `www/**`, `scripts/**`, `lib/fe/src/**`, and configuration/build/repository hygiene.

**Out of scope:** the PRDL framework (`lib/prdl/`, BAFTA's own microframework — trusted infrastructure) and the internals of the `pal_*` Paillier extension (reviewed separately). How our code *uses* both is in scope.

## Method

Four independent audits were run in parallel, each owning one dimension — (1) backend API & request handling, (2) cryptography, packing math & key handling, (3) server-rendered output & injection, (4) frontend Svelte/JS — followed by first-hand verification of every substantive claim. Each finding below that is marked **Confirmed** was reproduced directly, either by close reading of the exact code path or by executing PHP/GMP against the live extension. Repro transcripts are summarised inline.

## Bottom line

**Across most of its surface, the code is sound and secure as far as it goes.** SQL injection is not reachable (all queries are parameterised through PRDL's escaping layer); the server-rendered HTML is correctly escaped at every sink; the honest client encrypts exactly 0/1 with a properly CSPRNG-randomised Paillier library and never logs, stores, or displays plaintext; there are no DOM-XSS sinks; the packing/shift arithmetic is mathematically correct for honest inputs; and the libsodium key-at-rest code is used without crypto-misuse.

**The exceptions are concentrated almost entirely in one place: the answer-submission and aggregation path** (`submitResponse.php` → `apertureApiTools.php` → `apertureClasses.php`). That path has two clear implementation defects that are exploitable through the code's own logic regardless of the disabled authentication:

- an **unauthenticated CPU-exhaustion DoS** (High) from an unvalidated `optionNum`, and
- an **aggregate-integrity bypass** (High) where a client submits its own `optionNum = 0` ciphertext and skips server-side packing entirely.

Both share a single root cause — `EncryptedAnswer::__construct` performs no validation of `optionNum` — and both close with one bounds check. Beyond those, there is a design-level integrity limitation (no enforcement that a submitted ciphertext encrypts 0/1) that is inherent to the reduced scope and should be *explicitly documented* rather than left implicit, plus a handful of Medium/Low robustness and hardening items.

So: the honest-path implementation is good; the adversarial-input handling on the one endpoint that accepts untrusted ciphertexts is where the real work is.

## Findings

| ID | Severity | Area | Summary |
|----|----------|------|---------|
| A1 | **High** | Aggregation | Unbounded `optionNum` → unauthenticated CPU-exhaustion DoS |
| A2 | **High** | Aggregation | Client-supplied `optionNum = 0` bypasses server packing, sets the whole aggregate contribution |
| A3 | Medium | Aggregation | No plaintext range enforcement — malicious client can encrypt non-0/1 values (design-level) |
| B1 | Medium | Request parsing | Unknown request keys copied verbatim → post-validation names injectable; defeats the question-ID gate even once re-enabled |
| A4 | Low | Aggregation | 16-bit slot overflow at 65,536 selections/option → silent carry into the next option |
| A5 | Low | Aggregation | Negative/overflowed decrypt silently mis-unpacked (`gmp_export` drops the sign) |
| B2 | Low | Error handling | Dead `__missing` check; malformed params throw uncaught `TypeError` (500, possible path disclosure) |
| C1 | Low | Encoding | `strToBin` base36-before-base64 heuristic can misparse a ciphertext |
| D1 | Low | Output | `json_encode` into `<script>` without `JSON_HEX_*` flags |
| D2 | Low | Installer | `dbName`/`dbUsername` interpolated into shell/SQL by the installer (admin CLI) |
| E1 | Low | Frontend | `NaN` userId bypasses the client-side "set a user ID" guard |
| E2 | Low | Frontend | Injected public key used with no validation |
| E3 | Low | Frontend | Submission stream has no timeout, size cap, or error catch |
| F1 | Info | Keys | Sodium key-at-rest scheme is self-defeating (irrelevant in the POC) |
| F2 | Info | Misc | Missing `Content-Type` on the stream; production sourcemaps; external Google Fonts fetch |

---

### A1 — Unbounded `optionNum` → unauthenticated CPU-exhaustion DoS · **High** · Confirmed

**Location:** `lib/apertureClasses.php:10-17` (constructor, no bounds), `:91-100` (shift loop); reachable via `normaliseAnswers` (`lib/apertureApiTools.php:171`) from `www/api/submitResponse.php:21`.

`EncryptedAnswer::__construct` rejects only `optionNum === null`; it never bounds the value against the documented 128-option cap or the question's real option count. In `generatePackedAnswer`, `neededAnswerShifts = optionNum - 1`, and the loop performs one homomorphic `pal_multiply` on a full-size ciphertext per `MAX_ANSWER_SHIFTS` (=3) steps. Iterations grow linearly with `optionNum`: `optionNum = 2_000_000_000` yields ~666 million `pal_multiply` calls for a *single* answer object, and a request may carry many such answers. `submitResponse.php` sets no `set_time_limit`, so one small JSON body converts into effectively unbounded server CPU.

Reachable with nothing more than a well-formed body:
```json
{"userToken":"1;1;1","questionIds":"1;1;1",
 "answers":[{"questionId":1,"encAnswers":[{"optionNum":2000000000,"encAnswer":"<any base64 ciphertext>"}]}]}
```

**Fix:** validate `optionNum` in `EncryptedAnswer::__construct` — require `1 <= optionNum <= 128` (or the actual option count for the question) and reject otherwise with a 400. This is the same bounds check that closes A2.

---

### A2 — `optionNum = 0` bypasses server packing · **High** · Confirmed (live)

**Location:** `lib/apertureClasses.php:10-17` (constructor accepts `0`), `:82-88` (early-return when an `optionNum == 0` answer is present), `:111-117` (`save` stores it); consumed by `www/api/testResults.php:26,33` and `www/test-detail.php`.

`generatePackedAnswer()` returns early if *any* submitted answer already carries `optionNum == 0`, trusting it as the pre-packed aggregate. The constructor's only guard is `$optionNum === null` — and `0 === null` is `false` — so a client can submit its own ciphertext at `optionNum = 0`, skip the server's shift-and-pack step, and have that raw ciphertext persisted as its packed contribution. `testResults.php` then folds it straight into the homomorphic sum. The packing step is the code's own mechanism for constraining each user to one slot per option; this bypass is total and trivial, and it is independent of the disabled HMAC — even a fully-authenticated user could do it.

Verified live against the extension: submitting a single `optionNum = 0` ciphertext encrypting `65535 | (65535 << 16)` produced a stored contribution decrypting to `[_, 65535, 65535]` — every slot pre-filled to maximum from one submission.

**Fix:** reject any submitted answer with `optionNum < 1`; the `optionNum = 0` packed row must be server-generated only.

---

### A3 — No plaintext range enforcement (non-0/1 values) · Medium · Confirmed (live) · design-level

**Location:** `lib/apertureClasses.php:81-103` (packs client ciphertexts unchecked); root cause is architectural — the server never sees plaintext.

Because the browser legitimately holds the *public* key and encrypts client-side, a malicious client can encrypt any value, not just 0/1. The extension validates ciphertext group-membership but never the plaintext, and nothing on the server checks the value either. The server scales each submitted option ciphertext into its 16-bit slot and sums, so a chosen plaintext is added directly to the aggregate:

- `Enc(1000)` for one option inflates that option's tally by 1000 from a single submission (ballot stuffing).
- `Enc(65536)` for option 1 carries straight out of slot 0 into slot 1 — verified live: the frequency distribution came back `[0, 0, 1, 0]`, i.e. the count landed in **option 2's** slot while option 1 read 0. One submission corrupts a *neighbouring* option's count.
- A value with bits across many slots corrupts several options at once.

This differs from A1/A2 in kind: it is not a coding slip but a consequence of the POC deliberately omitting the cryptographic machinery (client-supplied zero-knowledge range/ballot proofs, verified server-side) that a production privacy-preserving tally requires. There is no server-only mitigation. The point for this review is that it is **not currently documented** as a boundary. It should be — either accept and record it as a known limitation alongside the other POC simplifications, or scope the range-proof work. A2 makes the bypass trivial and total even without crafting values; A3 remains once A2 is fixed.

**Fix:** document as an accepted POC limitation, and/or plan client range proofs. Fixing A2 removes the trivial path but not the underlying gap.

---

### B1 — Post-validation key names injectable through the request parser · Medium · Confirmed

**Location:** `lib/apertureApiTools.php:25-37` — the catch-all `else { $reqParams[$paramName] = $paramData; }`; consumed at `www/api/submitResponse.php:21`.

`getRequestParameters` maps the *input* param `questionIds` to the *validated* output key `validatedQuestionIds` via `validateQuestionIds()`. But any unrecognised param name is copied verbatim into `$reqParams`. A client can therefore send the post-validation name `validatedQuestionIds` directly in the body; it falls through the `else` branch into `$reqParams['validatedQuestionIds']` **without passing through `validateQuestionIds()`**. `submitResponse.php:21` reads that key directly for its `in_array(...)` authorisation gate, and the attacker controls both the answer's `questionId` and the injected array, so the gate is trivially satisfied.

Verified by reproducing the parser's branch logic: an injected `validatedQuestionIds` survives into `$reqParams` unvalidated. Today the gate is already inert because the `validateQuestionIds` HMAC is a disabled POC control — but this is a distinct, code-level flaw: **re-enabling the signature check would not restore the control**, because the parser bypass sidesteps the validator entirely. It is a mass-assignment-style defect.

**Fix:** whitelist accepted input param names and drop unknown ones; never accept the internal post-validation names (`validatedQuestionIds`, `userId`, `searchCriteria`) from the request body.

---

### A4 — 16-bit slot overflow at 65,536 selections per option · Low · Confirmed

**Location:** `lib/apertureTools.php:2-3` (`BITS_PER_ANSWER = 16`, `ANSWER_MASK = 65535`), `:45-54` (`toFD`); no count guard anywhere.

Each option occupies a 16-bit slot (max 65,535). Summing honest packed values gives per-slot counts with no saturation check. Verified with GMP:
```
N=65535 -> slot0=65535 slot1=0
N=65536 -> slot0=0     slot1=1   (wraps; carries into option 2)
N=65537 -> slot0=1     slot1=1
```
At 65,536 selections of one option the count wraps to 0 and silently increments the next option's tally. The binding limit is the slot width, not the ~3072-bit modulus (128 options only reach bit 2047; honest aggregation would need ~2^2900 users to threaten `n`). An honest-path correctness ceiling, not an attack, but undocumented.

**Fix:** document the ~65k-per-option ceiling, or widen slots (trading against the max option count, since 32-bit slots × 128 options would exceed the modulus), or cap accepted responses per question.

---

### A5 — Negative/overflowed decrypt silently mis-unpacked · Low · Confirmed

**Location:** `lib/apertureTools.php:13-37` (`strToBin` → `gmp_export`), `:45-54` (`toFD`).

The extension decrypts into the signed range `(-n/2, n/2]` and returns a leading-`-` base-36 string for negatives. `strToBin` parses that to a negative GMP and then calls `gmp_export()`, which **ignores the sign** and returns only magnitude bytes; `toFD` unpacks the magnitude as positive, producing garbage counts with no error. Verified: a negative value round-tripped through `gmp_export`/`gmp_import` comes back as an unrelated positive number.

Honest 0/1 aggregation stays small and positive, so this never triggers on its own — but combined with A2/A3 a malicious client can drive the aggregate magnitude past `n/2`, forcing a negative decrypt that is then mis-unpacked. A latent correctness landmine that compounds the integrity findings.

**Fix:** in `toFD`, detect a leading `-` (or a negative GMP) before export and reject or handle it explicitly rather than relying on `gmp_export`.

---

### B2 — Dead `__missing` check; malformed params throw uncaught `TypeError` · Low · Confirmed

**Location:** `lib/apertureApiTools.php:39-48` (`__missing` computed but never read — confirmed by grep); `www/api/submitResponse.php:15,20`.

The `__missing` required-params machinery is assigned but nothing consumes it, so malformed input crashes instead of being rejected cleanly:
- omitting `answers` → `count(null)` throws `TypeError` (verified on PHP 8.4);
- omitting `questionIds` → `in_array(..., null)` throws `TypeError`;
- sending `questionIds` as a non-string → violates the `string` type hint during `getRequestParameters()`, which runs at include time *before* the endpoint's `try` block even exists.

`submitResponse.php`'s handler catches only `ApertureException`, so these escape as uncaught fatals → HTTP 500, and if `display_errors` is on in the deployment the message discloses file paths and stack context.

**Fix:** enforce `$reqParams['__missing']` (return 400 listing missing params) before use; type-guard the validators or catch `Throwable` at the boundary; ensure `display_errors` is off in production.

---

### C1 — `strToBin` base36/base64 disambiguation is heuristic · Low · Confirmed (mechanism)

**Location:** `lib/apertureTools.php:13-34`.

`strToBin` tries base-36, then base-64, then raw. GMP base-36 accepts uppercase letters (verified: `gmp_init("ABChello123", 36)` succeeds), which overlap the base-64 alphabet. The frontend emits standard base64 (`+`, `/`, `=`), so virtually every ciphertext contains a non-base36 character and falls through correctly — but a ciphertext whose base64 happens to contain none of `+ / =` (astronomically rare) would be misparsed as base36 and silently corrupted. It only ever damages the submitter's own answer (no cross-user or injection effect); this is fragile design rather than a live bug.

**Fix:** tag the encoding explicitly (a one-character prefix or a separate field), or standardise on base64url end-to-end and decode by declared type.

*(Cleared while here: the recently-fixed zero-value handling in `strToBin` holds — `toFD("0", 2)` returns all-zeros correctly, and no sibling zero bug exists.)*

---

### D1 — `json_encode` into `<script>` context without `JSON_HEX_*` flags · Low · Confirmed

**Location:** `www/user/questionnaire.php:16` and `:29`.

Question/option data and the public key are emitted as raw JSON inside an inline `<script>` block. PHP's default `json_encode` already escapes `/` (so `</script>` becomes `<\/script>`) and U+2028/U+2029, so the classic script-close and line-separator breakouts are **not** currently exploitable — and the injected strings are admin-seeded only (the sole writers of question/option text are the two `INSERT`s in `scripts/install.php`), so no user-controlled data reaches the sink. The residual gap: `<!--` and `<script` are not escaped by default, and this is the one sink whose safety rests entirely on data provenance rather than on encoding. If question/option text ever becomes user- or import-writable (a future survey-builder), it becomes a live stored-XSS-adjacent sink with no backstop.

**Fix:** add `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` at both call sites — correct-by-construction and future-proof.

---

### D2 — Installer interpolates `dbName`/`dbUsername` into shell/SQL · Low · Confirmed

**Location:** `scripts/install.php:14-22`, `:99-109`; the shell interpolation itself is in the out-of-scope `lib/prdl/installTools.php`.

`install.php` passes `dbName`/`dbUsername` from the admin config file, unescaped, into PRDL's `upgradeDb()`, which interpolates them into backtick `mysql -e "..."` shell commands. The generated password is md5-hex (safe charset), but a config value containing shell/SQL metacharacters would inject. The caller is an admin running a CLI installer with admin-authored config, so this is an admin-trust boundary, not a remote vector — hence Low — but it is worth hardening as an example of how our code uses the framework.

**Fix:** validate `dbName`/`dbUsername` against `^[A-Za-z0-9_]+$` in `install.php` before passing them on; resolve the config path within `config/` rather than concatenating `$_SERVER['PWD']`.

---

### E1–E3 — Frontend robustness · Low · Confirmed

**E1 — `NaN` userId bypasses the guard** (`lib/fe/src/stores/answers.svelte.js:17-19,68-71`; `App.svelte:87`). Clearing the numeric input binds `null`; `parseInt(null,10)` is `NaN`, and `userId == 0` is false for `NaN`, so submission proceeds with a `"NaN;..."` token. Fix: `if (!Number.isInteger(userId) || userId <= 0)`.

**E2 — Injected public key unvalidated** (`answers.svelte.js:11-15`; `App.svelte:5`). `base64ToBigint` output is fed straight into `new PublicKey(n, g)` with no check on decode success or bit length; a truncated/misconfigured key silently produces undecryptable ciphertexts. The key arrives in the same server-injected script as the app, so tampering implies page control (hence Low), but a config error would fail silently. Fix: assert decode and a minimum bit length, fail closed with a clear message.

**E3 — Submission stream has no timeout, size cap, or error catch** (`answers.svelte.js:119-158`; `App.svelte:19-25`). A server that never closes leaves the UI hung; a response with no newline grows the buffer unbounded; an early `return` on `status:"OK"` never calls `reader.cancel()`; and `handleSave`'s `try/finally` has no `catch`, so a network rejection clears the spinner with no user-visible failure. No plaintext leaks in any path. Fix: add `AbortSignal.timeout`, cap buffer length, `reader.cancel()` on early return, add a `catch`, and check `res.ok`.

---

### F1–F2 — Informational

**F1 — Sodium key-at-rest is self-defeating** (`scripts/generateTestKeys.php:28-40,60-67`). `sodium_crypto_secretbox` is used correctly (fresh per-encryption nonce, no reuse, Argon2 KEK), but the passphrase, salt, nonce, and ciphertext are all written into the same `lib/testKeys.php`, so anyone with the file re-derives the KEK — the encryption provides zero confidentiality. It protects the signing key, which is unused in the POC, so it is irrelevant to the stated purpose; noted only so the pattern is never carried into production key management.

**F2 — Minor:** `www/api/submitResponse.php` streams newline-delimited JSON but sets no `Content-Type` (defaults to `text/html`; harmless, but `application/x-ndjson` is correct); `lib/fe/vite.config.js:8` ships production sourcemaps (full readable source — fine for an open POC); `lib/fe/src/app.css:1` `@import`s Google Fonts, so each page load pings `fonts.googleapis.com` (visit metadata to a third party; no answer data). Self-host the font if that matters.

## Audited and cleared

The following were checked and found sound — recording them so the scope of the "secure as far as it goes" verdict is explicit:

- **SQL injection** — every first-party `$DB` call uses `?` placeholders (or array args for `IN (?)`), and PRDL's `buildSql` (`lib/prdl/Dbif.php:337-412`) escapes each value with `mysqli_real_escape_string` and quotes it. No string-concatenated queries in first-party code. Not reachable.
- **Server-rendered HTML output** — every HTML-context sink is `htmlspecialchars`-escaped or integer-cast; the client-side result builders in `test.php` use correct `escapeHtml`/`escapeAttr` helpers into the right contexts; the one user-controlled value reaching a sink (the raw ciphertext blob in `test-detail.php`) is base36-constrained *and* escaped. No request parameters are reflected into output; no header injection.
- **DOM XSS** — no `{@html}`, `eval`, `new Function`, or `document.write` in the frontend; the single `innerHTML` write is a static constant; all Svelte interpolations of `questionData` are auto-escaped.
- **Identity spoofing via body-injected `userId`** — `QuestionAnswers::save()` uses the `$USER_ID` global set from the token, not `$reqParams['userId']`, and rejects `userId <= 0`. No spoof beyond the disabled-HMAC boundary.
- **Honest-client crypto** — exactly 0/1 encrypted per option, CSPRNG randomness (`paillier-bigint` → `crypto.getRandomValues`), correct base64 packing; no plaintext logged, stored, or displayed.
- **Packing/shift arithmetic** — `MAX_ANSWER_SHIFTS = 3` keeps the `pal_multiply` factor ≤ 2^48 (no PHP-int overflow); the shift is `(optionNum-1)*16` with no off-by-one; 128 options land at bit 2047, within the modulus.
- **Encryption-key export round-trip** — base36→GMP→`gmp_export`→base64 (server) and `base64ToBigint` (client) agree on big-endian order; `n`/`g` have their top bit set, so no leading-zero loss.
- **Weak randomness** — `Math.random()` appears only in demo/test tooling; no cryptographic path depends on it.

## Known POC boundaries (context, not findings)

For completeness, these are the documented, intentional simplifications and are **not** counted as findings: disabled user-token HMAC verification and expiry; disabled question-ID signature verification; unimplemented request/key signing; master keys from a test file rather than secure storage; and the three-service split (Data Server / User Server / Key Guardian) collapsed into one codebase. A3 above is the one item adjacent to these that is *not* currently documented and should be added to the list.

## Recommended priority

1. **A1 + A2** (High, shared root cause) — add a single `1 <= optionNum <= <question option count>` bounds check in `EncryptedAnswer::__construct`, and reject `optionNum < 1` so the packed row is server-generated only. Closes the DoS and the packing bypass together.
2. **B1** (Medium) — whitelist request param names so post-validation keys can't be injected; do this before any future re-enabling of the signature checks.
3. **A3** (Medium, design) — decide: document as an accepted limitation now, and/or scope client range proofs.
4. **A5, B2, C1** (Low) — handle negative decrypts in `toFD`; wire up `__missing` and catch `Throwable` at the API boundary; make the encoding explicit in `strToBin`.
5. **D1, D2, E1–E3** (Low) — add the `JSON_HEX_*` flags; validate installer config values; tighten the frontend guards and stream handling.
