# StoryWriter

Full-stack Laravel 12 application for StoryWriter - an AI-powered platform for creating interactive children's digital storybooks.

## Features

### Web Application
- **Admin Dashboard**: Web-based admin panel for managing users and stories
- **User Authentication**: Laravel Breeze authentication with email verification
- **Analytics Dashboard**: Story generation metrics and user activity tracking
- **Story Management**: View and manage all user-generated stories

### API Backend
- **AI Story Generation**: Generate children's stories using Together AI
- **Image Generation**: Automatic cover art creation
- **Text-to-Speech**: Voice narration via ElevenLabs integration
- **Conversation AI**: ElevenLabs conversational AI proxy with signed URL authentication
- **REST API**: Versioned endpoints for story CRUD and AI services
- **API Authentication**: Laravel Sanctum token-based authentication

## Tech Stack

- Laravel 12 (PHP 8.2+)
- SQLite (local development)
- PostgreSQL (staging/production)
- Together AI (LLM & Image Generation)
- ElevenLabs (TTS & Conversational AI)
- Laravel Sanctum (API Authentication)
- Laravel Breeze (Web Authentication)
- AWS SDK (Parameter Store for secrets management)

## Quick Start

```bash
composer setup
composer dev
```

Visit `http://localhost:8000`

### Admin Access

The admin dashboard (`/dashboard`) requires authentication and the `is_admin` flag:
- Admin users have `is_admin = true` in the database
- Only admin users can access dashboard routes and analytics
- Create admin users via database seeder or manually set the flag

## Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- Together AI API key (for story generation)
- ElevenLabs API key (for TTS and conversation AI)

## Environment

### Local Development

Local development uses SQLite for the database (no setup required).

Copy `.env.example` to `.env` and add your API keys:

```env
DB_CONNECTION=sqlite
TOGETHER_API_KEY=your_key_here
ELEVENLABS_API_KEY=your_key_here
```

### Staging/Production (PostgreSQL + AWS Parameter Store)

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

## Routes

### Web Routes (Authentication Required)
- `GET /` - Welcome page
- `GET /dashboard` - Admin dashboard (requires `is_admin = true`)
- `GET /dashboard/analytics` - Analytics dashboard (requires `is_admin = true`)
- `GET /stories/{story}` - View story details
- `GET /profile` - User profile management

### API Routes
- `POST /api/login` - API authentication (returns Sanctum token)
- `POST /api/generate-story` - Generate a new story (authenticated)
- `POST /api/stories/generate` - Alternative story generation endpoint (authenticated)
- `GET /api/v1/stories` - List all stories
- `POST /api/v1/stories` - Create a new story
- `GET /api/v1/stories/{id}` - Get story details
- `PUT /api/v1/stories/{id}` - Update a story
- `DELETE /api/v1/stories/{id}` - Delete a story

### ElevenLabs Conversation API (Authenticated)
- `POST /api/conversation/sdk-credentials` - Get signed WebSocket URL for conversation
- `POST /api/conversation/proxy` - Proxy conversation requests
- `POST /api/conversation/tts` - Text-to-speech conversion
- `GET /api/conversation/voices` - List available voices
