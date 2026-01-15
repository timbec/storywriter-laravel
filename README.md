# StoryWriter API

Laravel 12 API backend for StoryWriter - an AI-powered platform for creating interactive children's digital storybooks.

## Features

- **AI Story Generation**: Generate children's stories
- **Image Generation**: Automatic cover art creation
- **Text-to-Speech**: Voice narration via ElevenLabs integration
- **REST API**: Versioned endpoints for story CRUD and AI services

## Tech Stack

- Laravel 12
- SQLite (default)
- Together AI (LLM & Image Generation)
- ElevenLabs (TTS)
- Laravel Sanctum (API Authentication)

## Quick Start

```bash
composer setup
composer dev
```

Visit `http://localhost:8000`

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- Together AI API key
- ElevenLabs API key

## Environment

### Local Development

Copy `.env.example` to `.env` and add your API keys:

```env
TOGETHER_API_KEY=your_key_here
ELEVENLABS_API_KEY=your_key_here
```

### Staging/Production (AWS Parameter Store)

In staging and production environments, API keys are loaded from AWS Systems Manager Parameter Store. This provides secure secret management without storing credentials in code or configuration files.

#### Required Environment Variables

Add these to your EC2 instance or deployment environment:

```env
AWS_SSM_ENABLED=true
AWS_DEFAULT_REGION=us-east-1
APP_ENV=staging  # or 'production'
```

#### Required SSM Parameters

Create the following SecureString parameters in AWS Parameter Store:

| Environment | Parameter Path | Description |
|-------------|----------------|-------------|
| Staging | `/storywriter/staging/ELEVENLABS_API_KEY` | ElevenLabs API key |
| Staging | `/storywriter/staging/TOGETHER_API_KEY` | Together AI API key |
| Production | `/storywriter/production/ELEVENLABS_API_KEY` | ElevenLabs API key |
| Production | `/storywriter/production/TOGETHER_API_KEY` | Together AI API key |

Create parameters using AWS CLI:

```bash
# Staging
aws ssm put-parameter \
  --name "/storywriter/staging/ELEVENLABS_API_KEY" \
  --value "your-elevenlabs-key" \
  --type SecureString

aws ssm put-parameter \
  --name "/storywriter/staging/TOGETHER_API_KEY" \
  --value "your-together-key" \
  --type SecureString

# Production
aws ssm put-parameter \
  --name "/storywriter/production/ELEVENLABS_API_KEY" \
  --value "your-elevenlabs-key" \
  --type SecureString

aws ssm put-parameter \
  --name "/storywriter/production/TOGETHER_API_KEY" \
  --value "your-together-key" \
  --type SecureString
```

#### IAM Role Permissions

The EC2 instance IAM role is managed by Terraform (`terraform/main.tf`). The role includes:
- `ssm:GetParameters`, `ssm:GetParameter`, `ssm:GetParametersByPath` for `/storywriter/*`
- `kms:Decrypt` for SecureString parameter decryption

Run `terraform apply` to create/update the IAM role and attach it to the EC2 instance.

#### Caching

Parameters are cached for 5 minutes (300 seconds) to minimize AWS API calls. To adjust:

```env
AWS_SSM_CACHE_TTL=300  # seconds (default: 300)
```

Set to `0` to disable caching (not recommended for production)
