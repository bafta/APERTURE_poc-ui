# External Security Review — Scoping Document

**Date:** 2026-08-26
**Prepared by:** the Aperture project team
**Applies to two repositories:** `paillier-php` (the Paillier PHP extension) and `aperturePoc` (the proof-of-concept application)

This document gives an external reviewer the context needed to review the Aperture codebase and defines precisely what is, and is not, in scope. Please read the **Scope limitations** and **POC status** sections carefully before starting — they are what stop time being spent on things we already know are absent by design.

---

## 1. Purpose of the engagement

We would like an independent security code review of the first-party code in the two repositories listed above. We have already carried out our own internal reviews of both (see **Prior work**, section 8); this engagement is to have a second, independent pair of eyes challenge and extend that work.

The central question is the same one our internal review used:

> **Within the limited functionality that has actually been built, is the code secure in and of itself?**

We are *not* asking "what would this need to be production-ready" — we know it needs a great deal more (see below). We are asking whether the code that *is* there is sound: free of injection, memory-safety issues, cryptographic-usage errors, logic flaws that corrupt or leak data, denial-of-service in the implemented paths, and similar defects reachable through the code's own behaviour.

## 2. Project context

**Aperture** is a privacy-preserving survey platform, funded under a BFI grant and developed by BAFTA. Its purpose is to collect **diversity and inclusion data** from individuals and produce **aggregate statistics** across a population — without any server ever holding or seeing an individual's plaintext answers.

This is achieved with **Paillier homomorphic encryption**, a partially-homomorphic scheme in which ciphertexts can be added together (and multiplied by a scalar) without decryption. Each respondent encrypts their answers in the browser with a public key; the server stores only ciphertexts; answers are homomorphically summed across respondents; and only the final aggregate is ever decrypted. In the intended production design a dedicated **Key Guardian** service holds the private key and re-encrypts results for the requester, so that no single service can compromise individual responses.

**This POC exists to demonstrate one thing:** that the *mechanics* work — that Paillier can be used to store encrypted diversity answers and that meaningful aggregate reporting can be produced from them without decrypting individuals. It is a feasibility demonstration, not a complete or deployable system. The full security of a real deployment would depend on substantial additional components that the POC does not contain (identity, authenticated key distribution, the separated services, key management, ballot-integrity proofs, and more — see section 6).

## 3. The two repositories and how they relate

| Repository | What it is | Language / stack |
|---|---|---|
| **`paillier-php`** | A PHP extension providing the `pal_*` functions used by the application. A thin Rust wrapper (`src/lib.rs`) around the `fast-paillier` crate, built as a `cdylib` via `ext-php-rs`. Exposes: `pal_generate_keys`, `pal_get_encryption_key_numbers`, `pal_encrypt`, `pal_encrypt_array`, `pal_decrypt`, `pal_decrypt_array`, `pal_add`, `pal_add_array`, `pal_multiply`. All keys/ciphertexts/values cross the boundary as base-36 strings. | Rust (extension), consumed from PHP 8.4 |
| **`aperturePoc`** | The proof-of-concept application: submission and aggregation endpoints, server-rendered result pages, a Svelte 5 front end that encrypts answers client-side, and an installer. Uses the `pal_*` extension plus libsodium, on MySQL, atop BAFTA's in-house **PRDL** framework. | PHP 8.4, Svelte 5 / Vite, MySQL |

`aperturePoc` depends on `paillier-php` at runtime (it calls the `pal_*` functions). The review should treat them as one system but respect the scope boundaries below (in particular, the extension's *wrapper* is in scope but the cryptographic library underneath it is not).

**Review these at the current `main` of each repository:**
- `paillier-php` @ `b82c7cd`
- `aperturePoc` @ `8156a0a`

(We will confirm the exact commits/tags with the reviewer at kick-off in case of further changes.)

## 4. Objectives — what we want from the review

1. Identify vulnerabilities and defects in the **implemented** first-party code (see in-scope list).
2. For the crypto specifically: confirm the `pal_*` wrapper uses the underlying library correctly and safely, and that the application's **packing / aggregation logic** is sound (correct encoding, no silent corruption or overflow, correct handling of adversarial input).
3. Assess the **trust boundaries** that the implemented code is responsible for: the HTTP request-handling and validation code, the ciphertext/parameter parsing, and the server-rendered output.
4. Challenge our internal findings and their fixes (section 8) — tell us if we got any severity wrong, missed a case, or introduced a regression.

## 5. In scope

### 5.1 `paillier-php`
- `src/lib.rs` — the entire wrapper: input parsing and validation, the `pal_*` function implementations, encoding/decoding, error handling, and the memory-safety of the Rust code.
- The extension's **API contract** as used by consumers (encoding, ranges, error semantics).
- `Cargo.toml` / `Cargo.lock` **as they pertain to our choices** (pinning, our own code's use of dependencies) — but not the dependencies' internals (see out of scope).
- Build/packaging **only insofar as it affects the security of what ships** (e.g. that the distributed artifact corresponds to the reviewed source).

### 5.2 `aperturePoc` (first-party code only)
- `lib/apertureApiTools.php`, `lib/apertureClasses.php`, `lib/apertureTools.php` — request parsing, validation, the packing/aggregation logic, encoding helpers.
- `www/api/submitResponse.php`, `www/api/testResults.php` — the live API endpoints.
- `www/index.php`, `www/test.php`, `www/test-detail.php`, `www/user/questionnaire.php` — server-rendered pages and their output encoding.
- `scripts/install.php`, `scripts/generateTestKeys.php` — installer and key-generation (the demo scripts `demo.php` / `demoPacking.php` are presentation tools and lower priority, but note anything egregious).
- `lib/fe/src/**` — the Svelte front end: client-side encryption logic, submission handling, and DOM output.
- How our code **uses** PRDL, the `pal_*` extension, libsodium, and the front-end npm libraries (correct/safe usage is in scope even though the libraries themselves are not).

### 5.3 Cross-cutting concerns in scope
Injection (SQL, command, HTML/JS/JSON), cross-site scripting, cryptographic-usage errors, insecure/incorrect encoding and serialization, input-validation gaps, logic flaws affecting data integrity or confidentiality, denial-of-service reachable through the implemented code, information disclosure through errors, and memory safety (in the Rust wrapper).

## 6. Scope limitations (out of scope)

Please **do not** spend time on the following. Several are deliberate simplifications we are already aware of; reporting them back to us as findings is not useful, though we welcome a note if you believe one interacts with an *implemented* defect.

### 6.1 Third-party code we rely on
- **PRDL** (`lib/prdl/`) — BAFTA's in-house PHP microframework (DB access, auth, logging, validation, installer helpers). It is shared infrastructure used across many BAFTA products and is **out of scope**. Any real implementation would use the implementer's framework of choice. (Our code's *use* of PRDL — e.g. that we call its query API safely — is in scope.)
- **`fast-paillier`** — the Rust crate that implements the actual Paillier cryptography. **Out of scope**: we are asking you to review our *wrapper*, not the cryptographic library. (For reference it is pinned to an exact commit of a fork, `github.com/bafta/fast-paillier` @ `beb2c448…`; auditing that fork's diff against upstream is a separate exercise we have already noted internally.)
- **`ext-php-rs`**, **`rand_core`**, and other Rust crates; **`paillier-bigint`**, **`bigint-conversion`**, **Svelte**, **Vite** and other npm packages — the third-party libraries themselves are **out of scope**. Our *usage* of them is in scope.

### 6.2 Deliberately-omitted production functionality (known POC simplifications)
The following are intentionally not implemented in the POC. They are documented in each repo's README and are **out of scope** as "missing" findings — we know. What *is* in scope is whether the code that stands in for them today is itself sound (e.g. the request parser that will one day host the signature check must not be bypassable).
- User-token **HMAC verification** and **token expiry** are disabled.
- **Question-ID signature verification** is disabled.
- **Request/key signing** (libsodium signatures over requester keys and request bodies) is designed but not implemented; API inputs are trusted as-is.
- **Master keys are loaded from a test file** (`testKeys.php`) rather than from secure storage; there is no production key-management, rotation, or zeroization.
- The **three logical services** (Data Server / User Server / Key Guardian) are collapsed into one codebase; only the Data Server role is implemented. Decryption currently happens in the same process that stores ciphertexts.
- **Ballot-integrity / range proofs.** Because encryption is client-side with the public key, a dishonest client can encrypt values other than 0/1 and skew the aggregate. Preventing this requires client-supplied zero-knowledge range/ballot proofs verified server-side — a significant cryptographic subsystem that is **out of scope for the POC**. There is no server-only mitigation (the server never sees plaintext). Please do not report "clients can submit out-of-range values" as a finding; do report any *other* integrity flaw in how submissions are handled.

### 6.3 Cryptographic and infrastructural assumptions
- **Strength/choice of the underlying primitives** — the security of Paillier itself, the chosen key sizes, and the libsodium primitives — is assumed and is **out of scope**. Correct *usage* of them is in scope. The protocol design has already been independently reviewed (see section 8).
- **Infrastructure and deployment** — web-server (Apache/nginx) configuration, TLS, MySQL hardening, OS, network, and any live-environment penetration testing — is **out of scope**. This is a source-code review; there is no hosted environment to test against.
- **Compliance / legal** — this is not a GDPR or data-protection compliance assessment, though the project's purpose is privacy.
- **Built/generated artifacts** — the committed front-end bundle (`lib/fe/dist/**`) is generated from `lib/fe/src/**`; review the source, not the build output. The committed extension binary, if present, is likewise a build product of `src/lib.rs`.

## 7. Threat model and assets

The assets we care about, in priority order:
1. **Confidentiality of individual responses.** No individual's plaintext answer should be recoverable by the server, by another respondent, or by an attacker with database access — within the mechanics the POC implements.
2. **Integrity of aggregates.** A respondent (honest or malicious, using only the public key and the implemented endpoints) should not be able to silently corrupt or bias the aggregate beyond what the absence of range proofs already permits (that gap is out of scope; other integrity flaws are in scope).
3. **Availability.** The implemented endpoints should not be trivially DoS-able through the code (e.g. a single small request causing unbounded server work).
4. **No classic web vulnerabilities** — injection, XSS, etc. — in the implemented code.

Assumed adversaries: an unauthenticated or authenticated remote client of the API; a respondent using a tampered front end; and (for confidentiality) an attacker who has obtained a copy of the stored ciphertext database. Physical, social-engineering, and infrastructure-level adversaries are out of scope.

## 8. Prior work / handover materials

We have already performed internal security reviews of both repositories. Please read these — both to avoid re-treading ground and, more importantly, to **challenge** our conclusions and fixes:

- **Design review (February 2026):** Radically Open Security carried out an independent cryptographic review of the Aperture protocol design paper, concluding that the primitives and constructions are appropriate and correctly applied under a semi-honest adversary model, and recommending zero-knowledge proofs and malicious-adversary security as future work. The POC implements a simplified subset of that design (see section 6). Both the report and the paper it reviewed are in the public APERTURE repository: https://github.com/bafta/APERTURE/blob/main/External%20Review.pdf and https://github.com/bafta/APERTURE/blob/main/Architectural%20Overview.pdf. This engagement is about the *code*; the design itself does not need re-reviewing.
- **`paillier-php`**: `docs/security_review_20260706.md` (findings) and `docs/security_review_20260706_response.md` (fixes, one commit per finding). All findings were remediated.
- **`aperturePoc`**: `docs/security_review_20260826.md` (findings) and `docs/security_review_20260826_response.md` (fixes, one commit per finding). All findings were remediated.

We would specifically value your view on: whether any remediation is incomplete or introduced a regression; whether any severity rating is wrong; and whether the "secure as far as it goes" framing led us to under-weight anything within the implemented scope.

## 9. Practicalities

- **Access:** both repositories are held in BAFTA's GitHub organisation; we will arrange read access (or provide a source bundle) at kick-off.
- **Building `paillier-php`:** it is a Rust `cdylib` built against PHP 8.4 headers via `ext-php-rs`; the repo README documents the required toolchain and environment. A prebuilt extension can be provided if preferred. Note that Paillier key generation is slow (minutes); the test suite uses a cached keypair.
- **Running `aperturePoc`:** requires the `pal_*` extension and libsodium, PHP 8.4, MySQL, and Node/npm for the front end; `scripts/install.php` sets up the schema and seed data. A walkthrough can be provided.
- **Reporting:** we would like findings with, for each: a clear title, severity (calibrated to the POC lens described in section 1), exact location(s), a concrete description and reproduction/impact, and a recommended remediation. A short executive summary and an explicit list of what was reviewed and cleared would also be valuable. Please flag any assumption you had to make about the out-of-scope boundaries.
- **Points of contact / timeline / rules of engagement:** _to be completed with the reviewer at kick-off._

---

*This document defines the agreed scope. If during the review you find something that appears serious but sits on or beyond a scope boundary, please raise it with us rather than silently dropping it — we would rather hear about it and decide together.*
