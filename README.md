# BileMo

## Description

BileMo API provides access to a catalog of high-end mobile phones.

## Sommaire

- [Description](#description)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database](#database)
- [Usage](#usage)
- [Cache](#cache)
- [Security](#security)
- [Documentation](#documentation)
- [Endpoints](#endpoints)

## Installation

1. Clone the repository:
    ```bash
    git clone https://github.com/meline-p/bilemo.git
    cd bilemo
    ```

2. Install dependencies with Composer:
    ```bash
    composer install
    ```

3. Configure the environment by copying the `.env` file:
    ```bash
    cp .env .env.local
    ```

## Configuration

- `APP_SECRET` : Generate a random secret key
    ```bash
    php bin/console secrets:generate-keys
    ```
- `DATABASE_URL` : Database connection URL.
- JWT Token: Create a `jwt` folder in `config` directory. In your GitBash terminal, generate a private and public key:
    private key: 
    ```bash
    openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
    ```

    public key: 
    ```bash
    openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
    ```

    Change `JWT_PASSPHRASE` to your passphrase

Ensure these parameters are configured in the `.env.local` file.


## Database

1. Update the database:
    ```bash
    php bin/console doctrine:migrations:migrate
    ```

2. (Optional) Load test data:
    ```bash
    php bin/console doctrine:fixtures:load
    ```


## Usage

### Launch the website
- Start the local server: 
    ```bash
    symfony serve
    ```

- Access the application via your browser at http://localhost:8000.


## Cache

- This API uses `TagAwareCacheInterface` for cache management. To clear or invalidate the cache by tag, you can use the following command: 
    ```bash
    php bin/console cache:clear
    ```

- Or to invalidate a specific tag: 
    ```bash
    $cachePool->invalidateTags(['your_tag']);
    ```


## Security

JWT tokens are employed to protect the API routes.

- To generate a JWT token after authentication, send a POST request to:
    ```bash
    POST /api/login_check
    ```

- Add the token to the Authorization header to access protected endpoints:
    ```bash
    Authorization: Bearer your_jwt_token
    ```


## Documentation

The API is documented using `NelmioApiDocBundle`. After starting the server, the documentation is available at: http://localhost:8000/api/doc


## Endpoints
- GET `/api/products`
- GET `/api/products/{id}`

- GET `/api/users`
- POST `/api/users`
- GET `/api/users/{id}`
- PUT `/api/users/{id}`
- DELETE `/api/users/{id}`