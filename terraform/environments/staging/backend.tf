# Terraform S3 Backend Configuration for Staging
#
# The S3 bucket and DynamoDB table must already exist before running terraform init.
# Run the bootstrap configuration first: cd ../../bootstrap && terraform apply

terraform {
  backend "s3" {
    bucket         = "storywriter-terraform-state"
    key            = "environments/staging/terraform.tfstate"
    region         = "us-east-1"
    encrypt        = true
    dynamodb_table = "storywriter-terraform-locks"
  }
}
