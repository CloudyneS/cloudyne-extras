# Cloudyne Extras
Extra functionality for Wordpress on Cloudyne Hosting

## Attribution
bz-projects/Easy-SVG
images-to-webp by KubiQ

## Functionality
### SVG Support
Adds SVG sanitation and support to Wordpress. Allows you to upload SVG images to the media library and use them in your posts and pages.

### WebP Support/Conversion
Allows you to convert existing images to the WebP Format. **This requires additional configuration on the web server-side.**
The conversion will save the images as *.webp, for example image.png.webp for image.png. This allows you to use the same image name and path in your HTML and the web server will automatically serve the WebP version if it exists.

Below are basic examples of how to configure some webservers.
```
# Nginx Configuration
location ~* \.(?:ico|gif|jpe?g|png)$ {
    expires 30d;
    add_header Vary Accept;
    try_files $uri.webp $uri =404;
}

# Nginx Unit Route Example
...
{
      "match": {
        "uri": [
          "*.jpg",
          "*.jpeg",
          "*.gif",
          "*.png"
        ]
      },
      "action": {
        "share": [
          "/app/web$uri.webp",
          "/app/web$uri"
        ],
        "fallback": {
          "pass": "applications/...."
        }
      }
}
...
# Apache Configuration example
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP_ACCEPT} image/webp
    RewriteCond %{DOCUMENT_ROOT}/$1.webp -f
    RewriteRule (.+)\.(jpe?g|png)$ $1.webp [T=image/webp,E=accept:1]
    ...
```
### SMTP Configuration via environment variables
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

### Header, Body and Footer Code
Insert additional code into the header tag of the site. Useful for adding tracking code such as Google analytics.