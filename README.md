# Keyshop

Keyshop is a digital game key marketplace built for the Eneba internship assignment. The demo has six games, two seller offers per game and prices in euros. Checkout simulates a payment and issues demo keys. [Open the live store](https://eneba-keyshop.vercel.app/)

## Try the store

1. Search for **Hads** to find *Hades*, or **hol** to find *Hollow Knight*.
2. Open **Game store**, select **Xbox** and sort by **Lowest price**. *Terraria* should appear first.
3. Open a game, compare the two sellers and add an offer to your cart.
4. Create a test account. The store returns you to your cart so you can complete the simulated payment. Open **My orders** to see the seller, price and demo key.
5. Sign out and sign back in with the same account. The key stays in your order history.

## Run locally

Install PHP 8.2 or later with PDO SQLite enabled. From the project directory, run:

~~~sh
php -S localhost:8080 -t public
~~~

On Windows, you can also run the included PowerShell script:

~~~powershell
.\start.ps1
~~~

Open [the local store](http://localhost:8080). The first API request creates and seeds the SQLite database in the server directory. The repository includes the compiled React frontend. After editing app.jsx, rebuild it with:

~~~sh
npm ci
npm run build
~~~

### Run with Docker

~~~sh
docker build -t keyshop .
docker run --rm -p 8080:8080 -v keyshop-data:/shop/server keyshop
~~~

## How it works

- **Homepage.** React requests the banner and game shelves separately. PHP reads the copy, featured games and display order from the database. Game IDs identify the cover images.
- **Search.** PHP first checks for partial title matches. For words of three or more characters, it also checks the Levenshtein distance against words in the title. For example, inserting one letter turns “Hads” into “Hades”. The browser filters and sorts the results.
- **Cart.** Each entry records a specific seller offer. The cart stays in browser storage while the buyer signs in.
- **Checkout.** PHP checks the session and reads each price from the database. It stores prices in cents and saves the order and its keys in one transaction. If an offer is unavailable, the transaction rolls back. Retrying the same checkout request returns the existing order.
- **Accounts.** PHP hashes passwords. A cookie holds a random session token, and the database stores its SHA-256 hash, the user ID and the expiry time. Order history uses the session to identify the buyer. Signing out deletes the session.

## Deployment

### Vercel and Turso

1. Create a Turso Cloud libSQL database and a database token with read and write access.
2. Import the Mantas-eng/eneba-keyshop repository into Vercel. Keep the default project root. The build and PHP function settings are in vercel.json.
3. Add TURSO_DATABASE_URL and TURSO_AUTH_TOKEN to the Production environment. Use a separate database if you enable preview deployments.
4. Deploy the project, or redeploy after changing environment variables. The first API request creates the tables and seeds the catalog.
5. Open /api.php?r=catalog to check the API, then complete a test purchase. After a redeployment, sign back in and check that the order is still there.

### Docker hosting

The container reads the port from PORT. To keep accounts and orders across deployments, mount a persistent volume and point DB_PATH to a writable database file on it.

## Artwork

Game covers and the Hades banner come from the Steam CDN. The artwork belongs to the respective game publishers.
