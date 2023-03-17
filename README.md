# Cloudyne Extras
Extra functionality for Wordpress on Cloudyne Hosting

## Functionality
### Environmental SMTP Configuration
This plugin allows you to configure SMTP settings based on the environment. This is useful for when you have customers that should be restricted to sending emails from a certain email address.
```bash
# SMTP Host to send email
SMTP_HOST='smtp.gmail.com'

# The port to use
SMTP_PORT=25

# Authentication settings
SMTP_AUTH=True
SMTP_USER='someuser@test.com'
SMTP_PASS='abcdefgh'

# Security Settings
SMTP_SECURE=False
SMTP_AUTOTLS=False
SMTP_STARTTLS=False

# Provide a default sender name and email
SMTP_FROM='default@mail.com'
SMTP_FROM_NAME='From Name'

# Restrict the user changing email settings to only allow certain domains and/or emails to use as sender
SMTP_ALLOWONLY_DOMAINS='domain.com,domain2.com,domain3.com'
SMTP_ALLOWONLY_EMAILS='mail1@user.com,mail2@user.com'

# Force the site to only use the specified email and sender name
SMTP_FORCE_FROM='forced@mail.com'
SMTP_FORCE_FROM_NAME='ForcedFromName'
```

### Header Code
Insert additional code into the header tag of the site. Useful for adding tracking code such as Google analytics.