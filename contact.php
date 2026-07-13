<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$user = current_user();
$stored = $user ? find_user_by_id((string)$user['id']) : null;
$error = '';
$sent = isset($_GET['sent']);
$topics = ['General inquiry','Membership','Account support','Order support','Accessibility request','Privacy question','Press or media','Licensing','Collaboration or guest feature','Events','Website problem'];
$requestedTopic = trim((string)($_GET['topic'] ?? ''));
if (!in_array($requestedTopic, $topics, true)) {
    $map = ['membership' => 'Membership', 'order' => 'Order support', 'accessibility' => 'Accessibility request', 'privacy' => 'Privacy question', 'licensing' => 'Licensing', 'collaboration' => 'Collaboration or guest feature', 'events' => 'Events'];
    $requestedTopic = $map[strtolower($requestedTopic)] ?? 'General inquiry';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!empty($_POST['website'])) {
        $error = 'The message could not be accepted.';
    } elseif (empty($_POST['privacy_acknowledged'])) {
        $error = 'Confirm that the information may be used to review and respond to your inquiry.';
    } else {
        $ipHash = hash('sha256', client_ip_address());
        $recent = 0;
        $cutoff = time() - 3600;
        foreach (read_json_array(CONTACTS_FILE) as $record) {
            if (($record['ipHash'] ?? '') === $ipHash && strtotime((string)($record['createdAt'] ?? '1970-01-01')) >= $cutoff) $recent++;
        }
        if ($recent >= 5) {
            $error = 'Several messages have already been submitted. Please wait before sending another.';
        } else {
            try {
                save_contact_submission($_POST);
                redirect('contact.php?sent=1');
            } catch (Throwable $exception) {
                $error = $exception->getMessage() ?: 'Your message could not be sent. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en" data-theme="midnight">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Contact Orgasmaphoria for accounts, memberships, orders, accessibility, media, licensing, collaborations, events, or general questions.">
  <meta name="robots" content="index,follow">
  <meta name="theme-color" content="#09070d">
  <meta name="color-scheme" content="dark">
  <meta property="og:type" content="website"><meta property="og:title" content="Contact | Orgasmaphoria"><meta property="og:description" content="Contact Orgasmaphoria for support and business inquiries."><meta property="og:image" content="assets/images/orgasmaphoria-og.jpg"><meta property="og:site_name" content="Orgasmaphoria">
  <title>Contact | Orgasmaphoria</title>
  <link rel="icon" href="assets/images/favicon.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <script defer src="assets/js/data.js"></script><script defer src="assets/js/app.js"></script>
</head>
<body data-page="contact">
<a class="skip-link" href="#main">Skip to content</a><header data-site-header></header>
<main id="main">
  <section class="page-hero"><div class="page-hero__inner"><div><p class="eyebrow">Contact Orgasmaphoria</p><h1>Start a conversation.</h1><p class="lede">Reach the team for member support, purchases, media, licensing, collaborations, accessibility, events, or general questions.</p></div><div class="page-hero__mark"><img src="assets/images/logo.webp" width="735" height="760" alt=""></div></div></section>
  <section class="section"><div class="wrap contact-layout contact-layout--production">
    <div class="contact-intro"><p class="eyebrow">Get in touch</p><h2>How can the team help?</h2><p>Select the closest topic and include the details needed for a useful response.</p><div class="contact-notes"><div><strong>Member and order support</strong><span>Include the email used for your account or purchase. Never send a password, authenticator code, recovery code, or payment-card number.</span></div><div><strong>Accessibility</strong><span>Describe the barrier and the format, adjustment, or accommodation that would help.</span></div><div><strong>Business inquiries</strong><span>Include the organization, project, intended use, timeline, and preferred contact method.</span></div></div><p class="fine-print"><?= e(CONTACT_RESPONSE_TIME) ?></p></div>
    <form class="form-card contact-form-card account-form" method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <div class="hidden-field" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div><p class="eyebrow">Send a message</p><h2>Contact form</h2></div>
      <?php if ($sent): ?><div class="account-alert account-alert--success" role="status"><strong>Your message was received.</strong><br>The team can review it securely and respond using the email you provided.</div><?php endif; ?>
      <?php if ($error): ?><div class="account-alert account-alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <div class="form-grid">
        <label><span>Name</span><input type="text" name="name" autocomplete="name" required maxlength="100" value="<?= e($_POST['name'] ?? ($stored['displayName'] ?? '')) ?>"></label>
        <label><span>Email</span><input type="email" name="email" autocomplete="email" required maxlength="254" value="<?= e($_POST['email'] ?? ($stored['email'] ?? '')) ?>"></label>
        <label class="form-grid__wide"><span>Topic</span><select name="topic" required><?php foreach ($topics as $topic): ?><option value="<?= e($topic) ?>"<?= (($_POST['topic'] ?? $requestedTopic) === $topic) ? ' selected' : '' ?>><?= e($topic) ?></option><?php endforeach; ?></select></label>
        <label class="form-grid__wide"><span>Subject</span><input type="text" name="subject" required maxlength="150" value="<?= e($_POST['subject'] ?? '') ?>"></label>
        <label class="form-grid__wide"><span>Message</span><textarea name="message" rows="9" required minlength="10" maxlength="5000" placeholder="Tell us how we can help."><?= e($_POST['message'] ?? '') ?></textarea><small>10–5,000 characters.</small></label>
      </div>
      <label class="check-row"><input type="checkbox" name="privacy_acknowledged" required><span>I understand this information will be used to review and respond to my inquiry.</span></label>
      <button class="button button--primary" type="submit">Send message</button>
    </form>
  </div></section>
</main>
<footer data-site-footer></footer>
</body></html>
