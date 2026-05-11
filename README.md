# Aperture POC

A proof-of-concept privacy-preserving survey platform. Users submit encrypted responses to questions, and the system computes aggregate statistics across responses — without the server ever decrypting any individual answer.

This is achieved using [Paillier homomorphic encryption](https://en.wikipedia.org/wiki/Paillier_cryptosystem), which allows ciphertexts to be summed directly. Only the final aggregate is decrypted, and only by a dedicated Key Guardian service that re-encrypts the result with the requester's own public key.

## How It Works

1. The user's browser generates a Paillier keypair locally
2. Each survey answer (0 or 1 per option) is encrypted client-side before submission
3. The server stores only ciphertexts — it never sees plaintext answers
4. When statistics are requested, the server homomorphically sums the ciphertexts for a given cohort
5. A Key Guardian service decrypts the sum, extracts the frequency distribution, and re-encrypts it with the requester's public key
6. Only the requester can decrypt the result

## Architecture

The system is designed as three logically separate services:

| Service | Responsibility |
|---|---|
| **Data Server** | Stores and aggregates encrypted answers |
| **User Server** | Manages user identities and cohort membership |
| **Key Guardian** | Holds the master private key; decrypts aggregates and re-encrypts for requesters |

This separation means no single service can compromise individual responses: the Data Server has the ciphertexts but not the key, and the Key Guardian has the key but only ever sees aggregated ciphertexts.

### Answer Packing

Multiple options for a single question are packed into one Paillier ciphertext using 16-bit chunks within the 2048-bit plaintext space. This allows up to 128 options per question while requiring only one ciphertext per user per question. Homomorphic summation across users then produces a packed aggregate that the Key Guardian unpacks into a per-option frequency distribution.

## Stack

**Backend:** PHP with a custom Paillier extension (`pal_*` functions) and libsodium
**Frontend:** Svelte 5, built with Vite; uses `paillier-bigint` for client-side encryption
**Database:** MySQL
**Framework:** PRDL (internal PHP framework providing DB, auth, logging, and validation utilities)

## Setup

### Requirements

- PHP with the `pal_*` Paillier extension and libsodium
- MySQL
- Node.js / npm (for frontend development)

### Installation

1. Configure the site by copying and editing the config file:
   ```bash
   cp config/aperture.drbobchoco.com.php config/yoursite.php
   # edit config/yoursite.php with your DB credentials and site URL
   ```

2. Set the `CONFIG_LOCATION` environment variable to point to your config file (e.g. in your Apache/nginx vhost or `.env`).

3. Run the installer:
   ```bash
   php scripts/install.php config/yoursite.php
   ```

4. Generate test Paillier and sodium keys:
   ```bash
   php scripts/generateTestKeys.php
   ```
   This writes `lib/testKeys.php` containing two Paillier keypairs (master and user) and a sodium signing keypair. **Note:** Paillier key generation involves finding large primes and is very slow — expect 10+ minutes.

5. Build the frontend:
   ```bash
   cd lib/fe
   npm install
   npm run build
   ```

### Frontend Development

```bash
cd lib/fe
npm run dev      # dev server with HMR
npm run watch    # watch mode, outputs to dist/
npm run build    # production build
```

The built `bundle.js` is symlinked into `www/user/javascript/` and served by `www/user/questionnaire.php`.

## Web Pages

| Path | Description |
|---|---|
| `/` | Landing page with links to the questionnaire and results |
| `/user/questionnaire.php` | Svelte-powered survey form; encrypts answers client-side and submits them to the Data Server |
| `/test.php` | Aggregated results page; loads and decrypts per-question frequency distributions on demand |
| `/test-detail.php?questionId=X` | Bitfield detail page; decrypts each respondent's packed ciphertext individually and displays a per-user breakdown table with binary representations and raw ciphertext toggle |

## API Endpoints

| Endpoint | Service | Description |
|---|---|---|
| `POST /api/submitResponse.php` | Data Server | Submit encrypted answers for a set of questions |
| `GET /api/requestStatistic.php` | Data Server | Request a frequency distribution for a question over a cohort |
| `GET /api/lookupCohort.php` | User Server | Return user IDs matching search criteria |
| `GET /api/requestTranscription.php` | Key Guardian | Decrypt an aggregated ciphertext and re-encrypt for the requester |
| `GET /api/testResults.php?questionId=X` | Data Server | Decrypt and return aggregate results for a question (used by the results page) |

## Demo Scripts

Interactive CLI scripts for presentations and testing:

- **`php scripts/demo.php`** — walks through Paillier encryption, randomisation, and homomorphic addition step by step with coloured terminal output
- **`php scripts/demoPacking.php`** — demonstrates the 16-bit answer-packing scheme using plain arithmetic (no Paillier), showing how individual 0/1 answers are shifted into slots, combined, summed across respondents, and unpacked into a frequency distribution

Both scripts are interactive (press Enter to advance) and require `lib/testKeys.php` to be generated first.

## POC Status

This is a proof of concept. Several production concerns are intentionally simplified:

- User token HMAC verification and expiry checking are disabled
- Master Paillier keys are loaded from a test file rather than secure storage
- The three services run within a single codebase rather than as isolated deployments
- Error responses are generic
