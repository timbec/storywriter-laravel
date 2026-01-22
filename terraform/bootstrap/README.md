# Terraform Bootstrap

This directory contains the bootstrap configuration for Terraform state management infrastructure.

## Purpose

Creates the foundational resources needed for remote Terraform state:
- S3 bucket for storing Terraform state files
- DynamoDB table for state locking (prevents concurrent modifications)

## Usage

**IMPORTANT:** Run this configuration BEFORE applying any environment configurations (staging/prod).

### Initial Setup

```bash
cd terraform/bootstrap
terraform init
terraform plan
terraform apply
```

### What Gets Created

1. **S3 Bucket**: `storywriter-terraform-state`
   - Versioning enabled (protects against accidental deletions)
   - Server-side encryption enabled
   - Public access blocked
   - Lifecycle protection enabled

2. **DynamoDB Table**: `storywriter-terraform-locks`
   - Hash key: `LockID`
   - On-demand billing (pay-per-request)
   - Lifecycle protection enabled

### After Bootstrap

Once this is applied, your environment configurations (staging/prod) will use:
- The S3 bucket for storing state
- The DynamoDB table for preventing concurrent applies

The backend configurations in `environments/staging/backend.tf` and `environments/prod/backend.tf` reference these resources.

### Important Notes

- This configuration has `lifecycle.prevent_destroy = true` on critical resources
- To destroy these resources, you must first remove the lifecycle blocks
- Never delete the state bucket or DynamoDB table while environments are actively using them
