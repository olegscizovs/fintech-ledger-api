# Secure Headless FinTech Ledger API

A highly secure, stateless REST API designed to handle multi-currency ledger balances, transactional accounting, and automated financial report generation. Built using modern PHP 8.4+, Symfony 7.x, and Doctrine ORM using strict domain-driven design principles.

## 🚀 Key Architectural Features
- **Stateless Authentication:** Secure JWT-based authentication workflow with strict token refresh rotation.
- **Asynchronous Task Architecture:** Heavy resource operations (PDF statement compilation) are decoupled via Symfony Messenger and RabbitMQ.
- **Race Condition Prevention:** Implements explicit Doctrine pessimistic/optimistic locking mechanisms on account balances.
- **Strict Data Isolation:** Built under a strict Data Mapper architectural design to decouple DB schema from core Domain Rules.

## 🛠️ Tech Stack & Requirements
- **Core:** PHP 8.4+ (Strict types enforced)
- **Framework:** Symfony 7.x / API Platform
- **Database:** PostgreSQL 16+
- **Caching & Queue:** Redis / RabbitMQ
- **Environment:** Docker Compose (Nginx, PHP-FPM, Postgres, Redis)

## 📦 Installation & Local Setup

1. Clone the repository:
   ```bash
   git clone https://github.com
   cd fintech-ledger-api
   ```

2. Build and start the containerized environment:
   ```bash
   docker compose up -d --build
   ```

3. Initialize the application dependencies, database schema, and cryptographic vaults:
   ```bash
   docker compose exec php composer install
   docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
   docker compose exec php bin/console lexik:jwt:generate-keypair
   ```

4. Run the complete test suite to ensure system integrity:
   ```bash
   docker compose exec php bin/phpunit
   ```
