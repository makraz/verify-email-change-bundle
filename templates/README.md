# Email Change Templates

This directory contains production-ready Twig templates for implementing email change functionality in your Symfony application.

## 📧 Available Templates

### 1. EmailChangeVerification.tpl.php
**Purpose**: Verification email sent to the NEW email address

**Usage**:
```php
$email = (new Email())
    ->to($newEmail)
    ->subject('Verify Your New Email Address')
    ->htmlTemplate('email/email_change_verification.html.twig')
    ->context([
        'signature' => $signature,
        'user' => $user,
        'app_name' => 'My Application',
    ]);
```

**Required Variables**:
- `signature` - EmailChangeSignature object
  - `signedUrl` - The verification URL
  - `expiresAt` - Expiration timestamp
- `user` - User object with `email` property

**Optional Variables**:
- `app_name` - Your application name (default: 'Your Application')

**Features**:
- ✅ Responsive HTML design
- ✅ Professional gradient header
- ✅ Clear CTA button
- ✅ Expiration countdown
- ✅ Link fallback for email clients
- ✅ Security warnings
- ✅ Mobile-optimized

---

### 2. EmailChangeNotification.tpl.php
**Purpose**: Notification email sent to the OLD email address after successful change

**Usage**:
```php
$email = (new Email())
    ->to($oldEmail)
    ->subject('Your Email Address Was Changed')
    ->htmlTemplate('email/email_change_notification.html.twig')
    ->context([
        'old_email' => $oldEmail,
        'new_email' => $user->getEmail(),
        'changed_at' => new \DateTime(),
        'support_url' => $this->generateUrl('app_support', [], UrlGeneratorInterface::ABSOLUTE_URL),
        'app_name' => 'My Application',
    ]);
```

**Required Variables**:
- `old_email` - Previous email address
- `new_email` - New email address
- `changed_at` - DateTime object when change occurred

**Optional Variables**:
- `support_url` - Support contact URL (default: '#')
- `app_name` - Your application name (default: 'Your Application')

**Features**:
- ✅ Success confirmation message
- ✅ Detailed change information table
- ✅ Security warning for unauthorized changes
- ✅ Support contact button
- ✅ What-this-means explanation
- ✅ Mobile-optimized

---

### 3. twig_template.tpl.php
**Purpose**: User-facing form to request email change

**Usage**:
```php
#[Route('/account/email/change', name: 'app_email_change_request')]
public function request(Request $request): Response
{
    return $this->render('profile/change_email.html.twig');
}
```

**Required Routes**:
- `app_email_change_request` - Form submission handler
- `app_email_change_cancel` - Cancel pending change
- `app_profile` - Profile page (for redirect)

**Features**:
- ✅ Current email display (read-only)
- ✅ New email input with validation
- ✅ Password confirmation field
- ✅ Pending change indicator
- ✅ Flash message support (success/error)
- ✅ Cancel pending request button
- ✅ Security tips section
- ✅ Process explanation
- ✅ Tailwind CSS styling
- ✅ Fully responsive
- ✅ Accessible (ARIA labels, keyboard navigation)

---

## 🚀 Quick Setup

### Step 1: Copy Templates to Your Project

Using the `make:change-email-form` command:
```bash
php bin/console make:change-email-form
```

Or manually copy to your project:
```bash
# Copy templates
cp templates/EmailChangeVerification.tpl.php your-project/templates/email/email_change_verification.html.twig
cp templates/EmailChangeNotification.tpl.php your-project/templates/email/email_change_notification.html.twig
cp templates/twig_template.tpl.php your-project/templates/profile/change_email.html.twig
```

### Step 2: Customize

Edit the templates to match your branding:
- Colors and gradients
- Logo and images
- Copy and messaging
- Layout and styling

### Step 3: Integrate

Use in your controller (see documentation for full example).

---

## 🎨 Customization

### Change Colors

**Verification Email** (EmailChangeVerification.tpl.php):
```css
/* Line 28-33: Header gradient */
.header {
    background: linear-gradient(135deg, #YOUR_PRIMARY 0%, #YOUR_SECONDARY 100%);
}

/* Line 50-58: Button */
.button {
    background: #YOUR_PRIMARY;
}
```

**Notification Email** (EmailChangeNotification.tpl.php):
```css
/* Line 28-33: Header gradient */
.header {
    background: linear-gradient(135deg, #YOUR_PRIMARY 0%, #YOUR_SECONDARY 100%);
}
```

**Form Template** (twig_template.tpl.php):
```css
/* Line 9-11: Header gradient */
.gradient-header {
    background: linear-gradient(135deg, #YOUR_PRIMARY 0%, #YOUR_SECONDARY 100%);
}

/* Line 12-17: Button gradient */
.gradient-button {
    background: linear-gradient(135deg, #YOUR_PRIMARY 0%, #YOUR_SECONDARY 100%);
}
```

### Add Your Logo

Add to email templates:
```twig
<div class="header">
    <img src="{{ asset('images/logo.png') }}" alt="Company Logo" style="height: 40px; margin-bottom: 10px;">
    <h1>...</h1>
</div>
```

### Localization

Replace hard-coded strings with translations:
```twig
{# Before #}
<h1>Verify Your Email Change</h1>

{# After #}
<h1>{{ 'email_change.verification.title'|trans }}</h1>
```

---

## 📝 Template Variables Reference

### EmailChangeVerification.tpl.php

| Variable | Type | Required | Description |
|----------|------|----------|-------------|
| `signature.signedUrl` | string | Yes | Full verification URL |
| `signature.expiresAt` | DateTimeImmutable | Yes | Expiration timestamp |
| `user.email` | string | Yes | Current email address |
| `app_name` | string | No | Application name |

### EmailChangeNotification.tpl.php

| Variable | Type | Required | Description |
|----------|------|----------|-------------|
| `old_email` | string | Yes | Previous email |
| `new_email` | string | Yes | New email |
| `changed_at` | DateTime | Yes | When change occurred |
| `support_url` | string | No | Support contact URL |
| `app_name` | string | No | Application name |

### twig_template.tpl.php

| Variable | Type | Description |
|----------|------|-------------|
| `app.user.email` | string | Current email address |
| `app.user.pendingEmail` | string\|null | Pending email if exists |
| `app.flashes('success')` | array | Success messages |
| `app.flashes('error')` | array | Error messages |

---

## 🔒 Security Features

All templates include:
- ✅ Password confirmation required (form)
- ✅ Token expiration warnings
- ✅ Security notices
- ✅ Unauthorized change warnings
- ✅ Clear call-to-actions
- ✅ No sensitive data in URLs
- ✅ HTTPS-only links (when generated properly)

---

## 📱 Responsive Design

All templates are fully responsive:
- Desktop (1200px+)
- Tablet (768px - 1199px)
- Mobile (< 768px)

Email templates include:
- Mobile-optimized media queries
- Fluid layouts
- Touch-friendly buttons
- Readable font sizes on small screens

---

## ✉️ Email Client Compatibility

Email templates tested with:
- ✅ Gmail (Web, Mobile, Desktop)
- ✅ Outlook (2016, 2019, 365, Web)
- ✅ Apple Mail (iOS, macOS)
- ✅ Yahoo Mail
- ✅ Mozilla Thunderbird
- ✅ Samsung Email
- ✅ Proton Mail

---

## 🧪 Testing

### Email Testing Tools

Test emails locally:
```bash
# Using Mailpit
docker run -p 1025:1025 -p 8025:8025 axllent/mailpit

# Or MailHog
docker run -p 1025:1025 -p 8025:8025 mailhog/mailhog
```

Configure in `.env`:
```env
MAILER_DSN=smtp://localhost:1025
```

### Preview in Browser

To preview email templates:
1. Copy template content to a `.html` file
2. Replace Twig variables with sample data
3. Open in browser

---

## 📚 Documentation

- [Full Command Reference](../docs/MAKE_COMMAND.md)
- [Quick Example Guide](../docs/COMMAND_EXAMPLE.md)
- [Bundle Documentation](../README.md)
- [Controller Examples](../docs/COMMAND_EXAMPLE.md#step-3-create-controller)

---

## 🎯 Best Practices

1. **Always test** emails with real email clients
2. **Customize branding** to match your application
3. **Use translations** for internationalization
4. **Monitor delivery** rates and spam scores
5. **Keep templates updated** with security patches
6. **Test mobile** rendering thoroughly
7. **Include unsubscribe** links if required by law
8. **Log all email changes** for security auditing

---

## 🆘 Troubleshooting

**Emails not rendering correctly?**
- Check inline CSS is preserved
- Test with different email clients
- Validate HTML structure
- Use email testing tools

**Templates not found?**
```bash
php bin/console cache:clear
php bin/console debug:twig
```

**Styling issues?**
- Verify Tailwind CSS is loaded (form template)
- Check media queries in email templates
- Test on actual devices

---

## 📄 License

These templates are part of the makraz/verify-email-change-bundle package and are released under the MIT License.

---

## 🤝 Contributing

To improve these templates:
1. Test with multiple email clients
2. Ensure accessibility standards
3. Maintain mobile responsiveness
4. Update documentation
5. Submit pull request

---

**Questions?** See the [main documentation](../README.md) or [open an issue](https://github.com/Makraz/verify-email-change-bundle/issues).
