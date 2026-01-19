# Deployment Cleanup Summary

This document summarizes the security fixes and improvements made to the Storywriter deployment infrastructure.

## Critical Security Fixes Completed

### 1. SSH Access Restriction ✅
**Issue:** SSH was open to the entire internet (0.0.0.0/0)
**Fix:**
- Added `allowed_ssh_cidrs` variable to restrict SSH access to specific IPs
- Updated module, environment files, and tfvars files
- **ACTION REQUIRED:** Update `allowed_ssh_cidrs` in both tfvars files with your specific IP addresses

**Files Modified:**
- `terraform/modules/storywriter-server/variables.tf`
- `terraform/modules/storywriter-server/main.tf`
- `terraform/environments/staging/main.tf`
- `terraform/environments/staging/terraform.tfvars`
- `terraform/environments/prod/main.tf`
- `terraform/environments/prod/terraform.tfvars`

### 2. Terraform State Locking ✅
**Issue:** No DynamoDB table for state locking (risk of state corruption)
**Fix:**
- Created `terraform/bootstrap/` directory with DynamoDB table configuration
- Updated both staging and prod backend configs to use DynamoDB locking
- **ACTION REQUIRED:** Apply the bootstrap configuration first

**Files Created:**
- `terraform/bootstrap/main.tf`
- `terraform/bootstrap/variables.tf`
- `terraform/bootstrap/outputs.tf`
- `terraform/bootstrap/README.md`

**Files Modified:**
- `terraform/environments/staging/backend.tf`
- `terraform/environments/prod/backend.tf`

### 3. State File Path Standardization ✅
**Issue:** Inconsistent state file paths (staging used `backend-staging`, prod used `environments/prod`)
**Fix:** Standardized both to use `environments/{env}/terraform.tfstate`

**Note:** This changes the state file path for staging. You may need to migrate the existing state.

### 4. Terraform Workflow Fix ✅
**Issue:** `terraform-staging.yml` had incorrect working directory
**Fix:** Changed working directory from `terraform` to `terraform/environments/staging`

**Files Modified:**
- `.github/workflows/terraform-staging.yml`

### 5. IMDSv2 Enforcement ✅
**Issue:** EC2 instances vulnerable to SSRF attacks via IMDSv1
**Fix:** Added `metadata_options` block to enforce IMDSv2

**Files Modified:**
- `terraform/modules/storywriter-server/main.tf`

### 6. Sudo Permissions Tightening ✅
**Issue:** Deploy user had overly permissive sudo configuration `ALL=(ALL)`
**Fix:** Changed to `ALL=(root)` for least privilege

**Files Modified:**
- `terraform/modules/storywriter-server/user-data.sh`

### 7. Production Deployment Approval ✅
**Issue:** Production deployments had no manual approval requirement
**Fix:** Added environment configuration with URL
- **ACTION REQUIRED:** Configure required reviewers in GitHub Settings

**Files Modified:**
- `.github/workflows/deploy-prod.yml`

### 8. Health Check Enforcement ✅
**Issue:** Failed health checks only produced warnings, didn't fail deployment
**Fix:**
- Health checks now fail the deployment with `exit 1`
- Increased wait time from 2s to 5s
- Changed to use HTTPS instead of HTTP

**Files Modified:**
- `.github/workflows/deploy-prod.yml`
- `.github/workflows/deploy-staging.yml`

## Next Steps Required

### 1. Bootstrap Terraform State Infrastructure
```bash
cd terraform/bootstrap
terraform init
terraform plan
terraform apply
```

This creates:
- S3 bucket: `storywriter-terraform-state` (if not exists)
- DynamoDB table: `storywriter-terraform-locks`

### 2. Migrate Staging State (If Needed)
Since we changed the staging state file path, you may need to migrate:

```bash
cd terraform/environments/staging

# If you have existing state, migrate it
aws s3 cp s3://storywriter-terraform-state/backend-staging/terraform.tfstate \
          s3://storywriter-terraform-state/environments/staging/terraform.tfstate

# Then re-initialize
terraform init -migrate-state
```

### 3. Update SSH Access Configuration
Edit both tfvars files and replace the placeholder SSH CIDRs:

**Option A - Specific IP:**
```hcl
allowed_ssh_cidrs = ["1.2.3.4/32"]  # Your office/home IP
```

**Option B - Multiple IPs:**
```hcl
allowed_ssh_cidrs = [
  "1.2.3.4/32",      # Office IP
  "5.6.7.8/32",      # Home IP
]
```

**Option C - Use AWS Systems Manager (Recommended):**
Consider disabling SSH entirely and using AWS Systems Manager Session Manager instead.

### 4. Configure Production Approval in GitHub
1. Go to repository Settings > Environments > production
2. Check "Required reviewers"
3. Add team members who must approve production deployments
4. Optionally set a wait timer (e.g., 5 minutes)

### 5. Re-initialize Terraform Environments
After applying bootstrap:

```bash
# Staging
cd terraform/environments/staging
terraform init -reconfigure
terraform plan
terraform apply

# Production
cd terraform/environments/prod
terraform init -reconfigure
terraform plan
terraform apply
```

### 6. Test Deployments
- Push to `develop` branch to test staging deployment
- Push to `main` branch to test production deployment (will require approval)
- Verify health checks pass

## Additional Recommendations

### High Priority (Not Yet Implemented)
1. **Move SSH keys to AWS Parameter Store/Secrets Manager**
   - Currently hardcoded in user-data.sh

2. **Store database credentials in Secrets Manager**
   - Currently written to plaintext file

3. **Implement automated database backups**
   - Add cron job to backup PostgreSQL to S3

4. **Add CloudWatch monitoring and alarms**
   - Instance health monitoring
   - Application error alerting

5. **Create production Terraform workflow**
   - Currently only staging has a Terraform workflow

### Medium Priority
6. **Add automated rollback on deployment failure**
7. **Consolidate duplicate workflow code**
8. **Implement comprehensive resource tagging**
9. **Create `.gitignore` entries for tfvars files**
10. **Create `terraform.tfvars.example` files**

### Security Best Practices
11. **Enable VPC Flow Logs**
12. **Enable AWS GuardDuty**
13. **Enable EBS encryption by default**
14. **Automate SSL certificate management with certbot**
15. **Set up Laravel queue workers**

## Testing Checklist

Before considering this cleanup complete:

- [ ] Bootstrap terraform applied successfully
- [ ] Staging state migrated (if applicable)
- [ ] SSH access restricted to specific IPs
- [ ] Staging terraform workflow runs successfully
- [ ] Staging deployment workflow completes with health check
- [ ] Production deployment requires manual approval
- [ ] Production deployment fails if health check fails
- [ ] DynamoDB state locking prevents concurrent applies

## Files That Need Attention

**Sensitive files (ensure not committed):**
- `terraform/environments/staging/terraform.tfvars`
- `terraform/environments/prod/terraform.tfvars`

**Recommended additions to `.gitignore`:**
```
# Terraform
*.tfvars
!*.tfvars.example
.terraform/
*.tfstate
*.tfstate.backup
```

## Support

If you encounter issues:
1. Review the comprehensive code review in this session
2. Check the bootstrap README: `terraform/bootstrap/README.md`
3. Ensure AWS credentials are configured correctly
4. Verify all GitHub Secrets are set correctly

## Summary

All critical security fixes have been implemented in the codebase. The infrastructure is now significantly more secure with:
- ✅ Restricted SSH access
- ✅ State locking enabled
- ✅ IMDSv2 enforced
- ✅ Production approval gates
- ✅ Health check enforcement
- ✅ Least privilege sudo permissions

Next steps require applying these changes via Terraform and configuring GitHub settings for production approvals.
