<?php

declare(strict_types=1);

session_start();
header('X-Robots-Tag: noindex, nofollow', true);

$configPath = __DIR__ . '/admin-config.local.php';
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/admin-config.example.php';
}

$config = require $configPath;
$dataFile = __DIR__ . '/data/prices.json';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalize(string $value, int $maxLength = 180): string
{
    $value = trim($value);
    if (strlen($value) > $maxLength) {
        $value = substr($value, 0, $maxLength);
    }

    return $value;
}

function defaultPrices(): array
{
    return [
        'nightly' => [
            'twoPersons' => [
                'summer' => '€ 100,00',
                'winter' => '€ 110,00',
            ],
            'extraPerson' => 'Für jede weitere Person kommen 10€ pro Tag hinzu.',
        ],
        'fees' => [
            'cleaning' => '€ 70,00',
            'cityTax' => '€ 2,10 je Tag und Person ab 17 Jahren.',
            'dogs' => '€ 6,00 / Tag',
            'deposit' => '€ 100,00 nach Buchung',
            'balance' => 'Überweisung vor Reiseantritt oder bar bei Abreise. Keine Kartenzahlung möglich.',
        ],
        'updatedAt' => date('d.m.Y'),
    ];
}

function loadPrices(string $filePath): array
{
    if (!is_file($filePath)) {
        return defaultPrices();
    }

    $json = file_get_contents($filePath);
    if ($json === false) {
        return defaultPrices();
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return defaultPrices();
    }

    return array_replace_recursive(defaultPrices(), $decoded);
}

$authError = '';
$saveError = '';
$saveSuccess = '';

if (isset($_GET['logout'])) {
    unset($_SESSION['prices_admin_ok']);
    header('Location: /admin-prices.php');
    exit;
}

if (!isset($_SESSION['prices_admin_ok']) || $_SESSION['prices_admin_ok'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'login')) {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (
            hash_equals((string)($config['username'] ?? ''), $username) &&
            hash_equals((string)($config['password'] ?? ''), $password)
        ) {
            $_SESSION['prices_admin_ok'] = true;
            header('Location: /admin-prices.php');
            exit;
        }

        $authError = 'Login fehlgeschlagen.';
    }

    ?>
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Preisverwaltung Login</title>
    <style>
      body { font-family: Arial, sans-serif; margin: 0; padding: 24px; background: #f7f8f9; }
      .card { max-width: 420px; margin: 8vh auto; background: #fff; border: 1px solid #e2e2e2; padding: 22px; }
      h1 { margin: 0 0 14px; font-size: 1.5rem; }
      label { display: grid; gap: 6px; margin-bottom: 12px; font-weight: 600; }
      input { padding: 10px 12px; border: 1px solid #cfd4d8; }
      button { padding: 10px 14px; border: 1px solid #15779b; background: #15779b; color: #fff; cursor: pointer; }
      .error { color: #8f5151; margin-bottom: 10px; }
      .hint { font-size: .9rem; color: #666; margin-top: 14px; }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Preisverwaltung</h1>
      <?php if ($authError !== ''): ?>
        <p class="error"><?php echo h($authError); ?></p>
      <?php endif; ?>
      <form method="post" action="/admin-prices.php">
        <input type="hidden" name="action" value="login" />
        <label>
          Benutzername
          <input type="text" name="username" required />
        </label>
        <label>
          Passwort
          <input type="password" name="password" required />
        </label>
        <button type="submit">Anmelden</button>
      </form>
      <p class="hint">Konfiguration: admin-config.local.php</p>
    </div>
  </body>
</html>
    <?php
    exit;
}

$prices = loadPrices($dataFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'save')) {
    $updated = [
        'nightly' => [
            'twoPersons' => [
                'summer' => normalize((string)($_POST['nightly_two_summer'] ?? '')),
                'winter' => normalize((string)($_POST['nightly_two_winter'] ?? '')),
            ],
            'extraPerson' => normalize((string)($_POST['nightly_extra'] ?? ''), 280),
        ],
        'fees' => [
            'cleaning' => normalize((string)($_POST['fee_cleaning'] ?? '')),
            'cityTax' => normalize((string)($_POST['fee_city_tax'] ?? ''), 280),
            'dogs' => normalize((string)($_POST['fee_dogs'] ?? '')),
            'deposit' => normalize((string)($_POST['fee_deposit'] ?? '')),
            'balance' => normalize((string)($_POST['fee_balance'] ?? ''), 320),
        ],
        'updatedAt' => date('d.m.Y'),
    ];

    $encoded = json_encode($updated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        $saveError = 'Preise konnten nicht gespeichert werden (JSON-Fehler).';
    } elseif (file_put_contents($dataFile, $encoded . PHP_EOL, LOCK_EX) === false) {
        $saveError = 'Preise konnten nicht gespeichert werden (Datei nicht schreibbar).';
    } else {
        $saveSuccess = 'Preise wurden gespeichert.';
        $prices = $updated;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Preisverwaltung</title>
    <style>
      body { font-family: Arial, sans-serif; margin: 0; padding: 24px; background: #f7f8f9; }
      .card { max-width: 780px; margin: 0 auto; background: #fff; border: 1px solid #e2e2e2; padding: 22px; }
      h1 { margin: 0 0 6px; font-size: 1.6rem; }
      h2 { margin: 24px 0 10px; font-size: 1.2rem; }
      p.meta { margin: 0 0 14px; color: #666; }
      .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
      label { display: grid; gap: 6px; font-weight: 600; }
      input, textarea { padding: 10px 12px; border: 1px solid #cfd4d8; font: inherit; }
      textarea { min-height: 88px; resize: vertical; }
      .full { grid-column: 1 / -1; }
      .actions { margin-top: 18px; display: flex; gap: 10px; align-items: center; }
      button { padding: 10px 14px; border: 1px solid #15779b; background: #15779b; color: #fff; cursor: pointer; }
      .logout { color: #15779b; text-decoration: none; font-weight: 600; }
      .error { color: #8f5151; }
      .ok { color: #2f6d2f; }
      @media (max-width: 740px) { .grid { grid-template-columns: 1fr; } }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Preisverwaltung</h1>
      <p class="meta">Letzte Aktualisierung: <?php echo h((string)($prices['updatedAt'] ?? '-')); ?> · <a class="logout" href="/admin-prices.php?logout=1">Abmelden</a></p>

      <?php if ($saveError !== ''): ?>
        <p class="error"><?php echo h($saveError); ?></p>
      <?php endif; ?>
      <?php if ($saveSuccess !== ''): ?>
        <p class="ok"><?php echo h($saveSuccess); ?></p>
      <?php endif; ?>

      <form method="post" action="/admin-prices.php">
        <input type="hidden" name="action" value="save" />

        <h2>Ferienwohnung pro Nacht</h2>
        <div class="grid">
          <label>
            2 Personen Sommer
            <input type="text" name="nightly_two_summer" value="<?php echo h((string)($prices['nightly']['twoPersons']['summer'] ?? '')); ?>" required />
          </label>
          <label>
            2 Personen Winter
            <input type="text" name="nightly_two_winter" value="<?php echo h((string)($prices['nightly']['twoPersons']['winter'] ?? '')); ?>" required />
          </label>
          <label class="full">
            3-5 Personen Zusatztext
            <textarea name="nightly_extra" required><?php echo h((string)($prices['nightly']['extraPerson'] ?? '')); ?></textarea>
          </label>
        </div>

        <h2>Zusatzgebühren</h2>
        <div class="grid">
          <label>
            Endreinigung
            <input type="text" name="fee_cleaning" value="<?php echo h((string)($prices['fees']['cleaning'] ?? '')); ?>" required />
          </label>
          <label>
            Hunde
            <input type="text" name="fee_dogs" value="<?php echo h((string)($prices['fees']['dogs'] ?? '')); ?>" required />
          </label>
          <label class="full">
            Kurtaxe
            <textarea name="fee_city_tax" required><?php echo h((string)($prices['fees']['cityTax'] ?? '')); ?></textarea>
          </label>
          <label>
            Anzahlung
            <input type="text" name="fee_deposit" value="<?php echo h((string)($prices['fees']['deposit'] ?? '')); ?>" required />
          </label>
          <label class="full">
            Restzahlung
            <textarea name="fee_balance" required><?php echo h((string)($prices['fees']['balance'] ?? '')); ?></textarea>
          </label>
        </div>

        <div class="actions">
          <button type="submit">Speichern</button>
        </div>
      </form>
    </div>
  </body>
</html>
