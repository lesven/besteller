# HTTPS und CSRF-Token Problembehebung

## Problem: "Invalid CSRF token" bei HTTPS

### Ursachen:
1. **Session-Konfiguration**: Cookies werden nicht korrekt zwischen HTTP und HTTPS übertragen
2. **Trusted Proxies**: Reverse Proxy (nginx, Apache) überträgt HTTPS-Header nicht korrekt
3. **Stateless CSRF**: Stateless CSRF-Token funktionieren nicht richtig mit Sessions bei HTTPS

### Lösungen implementiert:

#### 1. Framework-Konfiguration (`config/packages/framework.yaml`)
- `csrf_protection: true` aktiviert
- `cookie_httponly: true` für bessere Sicherheit
- `trusted_proxies` und `trusted_headers` konfiguriert für Reverse Proxy

#### 2. CSRF-Konfiguration (`config/packages/csrf.yaml`)
- Stateless CSRF-Token deaktiviert (können bei HTTPS problematisch sein)
- Standard Session-basierte CSRF-Token verwendet

#### 3. Produktions-spezifische Konfiguration
- `config/packages/prod/framework.yaml`: Striktere Cookie-Einstellungen für HTTPS
- `config/packages/prod/security.yaml`: HTTPS-optimierte Security-Einstellungen

### Weitere Schritte für HTTPS-Deployment:

#### 1. Umgebungsvariablen setzen:
```bash
# In .env.prod oder als Umgebungsvariablen
APP_ENV=prod
HTTPS=true
```

#### 2. Reverse Proxy Konfiguration (nginx):
```nginx
server {
    listen 443 ssl;
    server_name your-domain.com;
    
    location / {
        proxy_pass http://your-app:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-Port 443;
    }
}
```

#### 3. Apache Configuration:
```apache
<VirtualHost *:443>
    ProxyPreserveHost On
    ProxyPass / http://your-app:8080/
    ProxyPassReverse / http://your-app:8080/
    
    # HTTPS Headers
    ProxyPassReverse / http://your-app:8080/
    ProxyPassReverseAdjust On
    ProxyAddHeaders On
    
    # Required headers for Symfony
    ProxyPassReverse / http://your-app:8080/
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"
</VirtualHost>
```

### Debugging:

#### 1. CSRF-Token prüfen:
```bash
# In Browser-Entwicklertools, Formulardaten prüfen:
# _csrf_token sollte einen Wert haben
```

#### 2. Session-Cookies prüfen:
```bash
# Browser-Entwicklertools > Application > Cookies
# PHPSESSID sollte gesetzt sein
# Secure-Flag sollte bei HTTPS gesetzt sein
```

#### 3. Symfony Debug:
```bash
# Logs prüfen
docker-compose exec php tail -f var/log/dev.log
```

### Sofortige Notlösung:
Falls CSRF weiterhin Probleme macht, temporär deaktivieren:

```yaml
# config/packages/security.yaml
form_login:
    enable_csrf: false  # NUR temporär für Tests!
```

**⚠️ WICHTIG**: CSRF-Schutz niemals dauerhaft deaktivieren in Produktion!
