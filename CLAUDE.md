# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Aperture is a privacy-preserving survey/data aggregation POC that uses **Paillier homomorphic encryption** to compute aggregate statistics on encrypted responses without ever decrypting individual answers. The system is designed as three logically separate services that communicate via API calls.

## Commands

### Frontend (Svelte + Vite)
Working directory: `lib/fe/`

```bash
npm run dev      # Development server with HMR
npm run build    # Production build → dist/assets/bundle.js
npm run watch    # Watch mode (build on changes)
npm preview      # Preview production build
```

The built `bundle.js` is symlinked from `www/user/javascript/bundle.js`.

### Backend (PHP)
No build step — PHP is interpreted. To install/initialize:
```bash
php scripts/install.php config/aperture.drbobchoco.com.php
```

The PHP backend requires the `pal_*` Paillier extension functions and libsodium.

## Architecture

### Three-Tier Service Design
The architecture separates concerns across three logical services (currently all in one codebase but designed to be split):

1. **Data Server** (`www/api/submitResponse.php`, `requestStatistic.php`) — stores and aggregates encrypted answers; never holds the decryption key
2. **User Server** (`www/api/lookupCohort.php`) — manages user identities and cohort membership
3. **Key Guardian** (`www/api/requestTranscription.php`) — holds the master Paillier private key; decrypts aggregated sums and re-encrypts with the requester's public key

### Cryptographic Flow
```
Client: encrypt each answer (0 or 1) with Paillier public key
    → Submit ciphertexts to Data Server
    → Data Server packs multiple answers per question into one ciphertext
    → Data Server homomorphically sums ciphertexts across users
    → Key Guardian decrypts the sum, unpacks frequency distribution
    → Key Guardian re-encrypts distribution with requester's public key
    → Client decrypts result with their private key
```

**Answer packing:** Up to 128 options per question are packed into a single 2048-bit ciphertext using 16-bit chunks (`BITS_PER_ANSWER = 16` in `lib/apertureClasses.php`). The packed ciphertext is stored at `optionNum=0`; individual encrypted options at `optionNum>0`.

### Key Files
- `lib/apertureClasses.php` — `EncryptedAnswer`, `EncryptedAnswerArray`, `QuestionAnswers` classes; packing/aggregation logic
- `lib/apertureTools.php` — encoding utilities (`strToBin`, `toFD` frequency distribution unpacker, `getEncryptedSum`)
- `lib/apertureApiTools.php` — request parsing, user/HMAC validation, key retrieval, JS public key export
- `lib/apertureDoApi.php` — inter-service HTTP calls and error handling
- `lib/fe/src/stores/answers.svelte.js` — frontend Svelte store; handles client-side Paillier encryption and API submission
- `lib/fe/src/components/Question.svelte` — renders individual questions with radio/checkbox inputs
- `lib/prdl/` — PRDL framework (DB interface, auth, logging, validation — treat as stable infrastructure)

### Frontend
Svelte 5 SPA served via PHP template (`www/user/questionnaire.php`). The PHP page injects the Paillier public key and question data as inline JSON; the Svelte store picks these up at init time. Uses `paillier-bigint` and `bigint-conversion` npm packages for client-side encryption.

### Configuration
Site config lives in `config/aperture.drbobchoco.com.php`. The `CONFIG_LOCATION` environment variable points the PHP startup (`lib/prdl/startup.php`) to the correct config file. DB: MySQL, `aperture` user.

## POC Shortcuts (Not Production-Ready)
Several security checks are intentionally disabled for POC simplicity — noted with comments in `lib/apertureApiTools.php`:
- User token HMAC verification is skipped
- Token expiry checking is disabled
- Question ID signature verification is disabled
- Master Paillier keys come from a test file (`testKeys.php`), not secure storage
