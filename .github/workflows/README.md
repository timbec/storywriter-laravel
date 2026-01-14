# Deployment Workflows

## Staging Deployment

**Trigger**: Automatic on push to `develop` branch, or manual via Actions tab

**Process**:
1. Build job: Install dependencies, build assets, create artifact (~2-3 min)
2. Deploy job: Backup current, deploy artifact, health check (~30-60 sec)

**Features**:
- Automatic backups before each deploy (keeps last 5)
- Health checks after deployment
- Deployment artifacts versioned by commit SHA

## Useful Commands

### Check deployment backups
```bash
ssh -i ~/.ssh/storywriter-staging-ec2-tf.pem ubuntu@34.194.100.158
ls -la /var/www/releases/
```

### Rollback to previous deployment
```bash
ssh -i ~/.ssh/storywriter-staging-ec2-tf.pem ubuntu@34.194.100.158
sudo -u deploy bash
cd /var/www/storywriter-staging

# See available backups
ls -la /var/www/releases/

# Restore from backup (replace TIMESTAMP)
cp -a /var/www/releases/backup_YYYYMMDD_HHMMSS/. .
sudo systemctl reload php8.4-fpm
```

### View application logs
```bash
ssh -i ~/.ssh/storywriter-staging-ec2-tf.pem ubuntu@34.194.100.158
sudo -u deploy tail -f /var/www/storywriter-staging/storage/logs/laravel.log
```

### Manual deployment trigger
GitHub → Actions → Deploy to Staging → Run workflow

## Secrets Required

- `SSH_PRIVATE_KEY`: Deploy user's private SSH key
- `STAGING_HOST`: EC2 server IP address (currently: 34.194.100.158)
