<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aperture</title>
    <link rel="stylesheet" href="./user/javascript/main.css">
    <style>
        .landing-links {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .landing-link {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            padding: var(--l-pod-pad);
            text-decoration: none;
            color: var(--c-text);
            background-color: var(--c-white);
            border: 1px solid var(--c-border);
            border-radius: var(--s-border-radius);
            box-shadow: var(--s-box-shadow);
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .landing-link:hover {
            border-color: var(--c-blue);
            box-shadow: 0 3px 20px rgba(20, 99, 255, 0.1);
            text-decoration: none;
        }
        .landing-link__title {
            font-size: var(--f-md);
            font-weight: 700;
        }
        .landing-link__desc {
            font-size: var(--f-xs);
            color: var(--c-grey-dark-2);
        }
    </style>
</head>
<body>
    <header class="site-header">
        <a class="site-header__logo" href="/">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
            <span>Aperture</span>
        </a>
    </header>

    <div class="page-body">
        <h1 class="page-title">Aperture POC</h1>
        <p class="page-subtitle">Privacy-preserving survey aggregation using Paillier homomorphic encryption.</p>

        <div class="landing-links">
            <a class="landing-link" href="/user/questionnaire.php">
                <span class="landing-link__title">Take the Survey</span>
                <span class="landing-link__desc">Submit your encrypted responses to the questionnaire.</span>
            </a>
            <a class="landing-link" href="/test.php">
                <span class="landing-link__title">View Results</span>
                <span class="landing-link__desc">Decrypt and view aggregated frequency distributions.</span>
            </a>
        </div>
    </div>
</body>
</html>
