fintech-ledger-api/
├── .github/workflows/       # CI/CD pipelines (PHPStan, PHPUnit automation)
├── config/                  # Framework configurations (routes, security.yaml)
├── docker/                  # Local development environment configurations
├── src/                     # Core application logic folder
│   ├── Common/              # Shared infrastructure utilities
│   │   └── Library/         # Reusable math tools or string helpers
│   │
│   ├── Authentication/      # Domain 1: Identity & Security
│   │   ├── Controller/      # Login/Refresh HTTP Endpoints
│   │   └── Entity/          # User and UserCredential database maps
│   │
│   ├── Account/             # Domain 2: Customer Wallets
│   │   ├── Controller/      # Open Account, Get Wallet Balance
│   │   └── Entity/          # Account database maps
│   │
│   └── Ledger/              # Domain 3: The Core Financial Engine
│       ├── Api/             # API Platform resources & Data Providers
│       ├── Command/         # CLI commands (e.g., reconcile:ledger)
│       ├── Entity/          # Transaction and LedgerEntry mapping definitions
│       ├── Exception/       # Custom domain exceptions (e.g., InsufficientFundsException)
│       ├── Message/         # Symfony Messenger async command objects
│       ├── MessageHandler/  # Handlers running asynchronously in the background
│       ├── Repository/      # Strict SQL/Doctrine calculation methods
│       └── Service/         # Pure business logic calculators (Double-entry validator)
│
├── tests/                   # Decoupled testing suite mirroring /src layout
│   ├── Authentication/      # JWT security and auth tests
│   ├── Account/             # Wallet behavioral verification
│   └── Ledger/              # Core financial calculation assertions
│       ├── Unit/            # Fast tests testing pure logic (0 database dependencies)
│       └── Functional/      # API tests checking Postgres write locks and status codes
│
├── docker-compose.yaml      # Multi-container network blueprint
├── MAIN.md                  # Project overview document
├── SKILLS.md                # Enterprise capabilities ledger
└── SAFETY.md                # AI agent evaluation guardrails