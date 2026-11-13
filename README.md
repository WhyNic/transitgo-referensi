# TransitGo

TransitGo is a secure bus and travel ticket booking demo for Question 1 of COMP6695001 Secure Programming. The interface is in Bahasa Indonesia. Fares are displayed in rupiah and schedules use WIB.

## Technology and requirements

- Native PHP 8.1 or later with PDO MySQL, mbstring, Fileinfo, and GD.
- MySQL 8.0.16 or later, or the MariaDB server bundled with XAMPP. Tables use InnoDB.
- Apache with PHP enabled. The PHP development server also works for a local demo.
- HTML, CSS, and vanilla JavaScript. JavaScript adds seat-selection feedback and delete confirmation.
- The PHP process needs permission to create and write the private storage directory. Set `upload_max_filesize = 6M` and `post_max_size = 8M` in `php.ini` so the app can enforce its own upload limits.

## Run with XAMPP on Windows

1. Put the project at `C:\TransitGo`, outside `C:\xampp\htdocs`. Start MySQL in the XAMPP Control Panel.
2. Open phpMyAdmin and import `database.sql` on a fresh database installation. The script creates the `transitgo` database, its tables, and fictional demo data. You can also use `SOURCE C:/TransitGo/database.sql` inside the MySQL client.
3. Create a database account for the app. In the SQL console of a database administrator, run the statements below with a local password of your choice.

   ```sql
   CREATE USER 'transitgo_app'@'localhost' IDENTIFIED BY 'replace-with-a-local-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON transitgo.* TO 'transitgo_app'@'localhost';
   ```

4. Copy `config/local.example.php` to `config/local.php` and enter the connection details. The sample uses port 3306.
5. In `C:\xampp\php\php.ini`, enable `extension=gd` if it is commented out. Confirm that PDO MySQL, mbstring, and Fileinfo are enabled.
6. Add this local virtual host to the end of `C:\xampp\apache\conf\httpd.conf`. Use a free port and adjust the path if you placed the project elsewhere. The document root must point to `public/` so configuration, SQL files, operator scripts, and uploaded images remain private.

   ```apache
   Listen 127.0.0.1:8080
   <VirtualHost 127.0.0.1:8080>
       ServerName localhost
       DocumentRoot "C:/TransitGo/public"
       <Directory "C:/TransitGo/public">
           Options -Indexes
           AllowOverride None
           DirectoryIndex index.php
           Require local
       </Directory>
   </VirtualHost>
   ```

7. Restart Apache from the XAMPP Control Panel and open [TransitGo locally](http://127.0.0.1:8080/).

For a local run with the PHP development server, complete the database and PHP configuration steps, then run this command from the project directory.

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t public
```

For an existing installation, back up the database and apply `migrations/provider-verification.sql` once with the database administrator account before running the updated code. The migration preserves accounts, bookings, routes, vehicles, and schedules. Existing provider accounts start incomplete and existing schedules become drafts until the provider is approved and publishes them. Existing bookings and tickets remain accessible.

## Demo accounts

All seeded accounts are fictional and share the local demo password `TransitGo!Demo2026`.

| Role | Email | Access |
| --- | --- | --- |
| Customer | `customer@example.com` | Search, reserve seats, simulate payment, and view own orders and tickets |
| Customer | `customer2@example.com` | Second account for testing booking access |
| Travel Provider | `provider@example.com` | Lintas Pagi Travel routes, vehicles, schedules, and orders |
| Travel Provider | `provider2@example.com` | Cakrawala Shuttle routes, vehicles, schedules, and orders |
| Admin | `admin@example.com` | Provider verification and read-only monitoring of users, providers, routes, schedules, and bookings |

Customers and providers have separate registration pages. The customer registration page links to **Daftar sebagai penyedia perjalanan**. All signed-in roles can update their own profile and photo. The provider profile uses the company name.

## Provider verification

1. Sign in as `provider@example.com` or register a provider. Update the company name and contact details from **Profil**.
2. From the provider dashboard, open **Kelola berkas perusahaan** and upload a logo and business license. Add a vehicle or select a seeded vehicle, then upload its photo, STNK, and KIR from **Kelola berkas kendaraan**. Use fictional sample files for the local demo. A clearly labeled fictional JPEG or PNG can be used for each document field.
3. Select **Ajukan verifikasi** when both company files and all three files for at least one vehicle are present. The provider starts **Belum lengkap** and moves to **Menunggu verifikasi**. The evidence and vehicle records are locked during review.
4. Sign in as `admin@example.com`, open **Verifikasi penyedia**, and inspect the pending application. Select **Setujui** or enter a short reason and select **Tolak**. Rejected providers see the reason on their dashboard and can correct their files and resubmit.
5. Sign back in as the approved provider and select **Terbitkan** for a draft schedule that uses a vehicle with complete files. For a quick booking demo, verify **Lintas Pagi Eksekutif** and publish its seeded Jakarta to Bandung departure on the day after import. Both seeded providers start incomplete and all seeded schedules start as drafts.

| Upload | Formats | Maximum size | Access |
| --- | --- | --- | --- |
| Company logo, vehicle photo | JPEG, PNG | 2 MB each | Owner and admin during review. Public after provider approval. |
| Business license, STNK, KIR | PDF, JPEG, PNG | 5 MB each | Owning provider and admin, through an authenticated download |

Uploads use server-side MIME detection and size checks. GD decodes and re-encodes JPEG and PNG uploads. PDFs must have a valid-looking header and cross-reference trailer and are sent as attachments. TransitGo assigns random filenames and keeps all uploads outside `public/`. Replacement keeps the previous file until the new file and its database update succeed. Upload, removal, submission, review, and publication actions require CSRF tokens.

Only approved providers can publish future schedules for their own vehicles with a photo, STNK, and KIR. Saving a schedule edit returns it to draft. Changes to approved verification files, the company name, or documented vehicle identity require another review. Previously published schedules are hidden while approval is incomplete and return after approval if their vehicle files are complete. Providers can hide a published schedule without affecting existing reservations or tickets.

## Main application flow

1. After approving a provider and publishing a departure, search for its route and date from the home page. Seeded departures are dated one to three days after import. Providers can add later departures through their dashboards.
2. Open a result and select one seat per passenger. The checkout shows the trip, collects a name for each seat, and displays the total. The server calculates the stored total from the schedule fare.
3. Create the booking and select **Simulasikan pembayaran**. TransitGo holds seats for up to 15 minutes or until departure, whichever comes first. After expiration the seat can be booked again.
4. View the e-ticket or reopen it from **Pesanan**. Payment is a local simulation.

Provider CRUD is available from the provider dashboard. Create a route and vehicle before scheduling them. Delete a schedule before removing its route or vehicle. Schedules with booking history retain their trip information. Changing a route’s cities or a vehicle’s capacity requires removing dependent schedules first.

## Demo password recovery

1. Open **Lupa kata sandi** and request a code for a demo account.
2. As the local operator, run this command from the project directory.

   ```powershell
   C:\xampp\php\php.exe scripts\reset-code.php customer@example.com
   ```

3. Enter the printed code on **Atur ulang kata sandi** with a new password. The code is valid for 15 minutes and can be used once. A successful reset invalidates the account’s existing sessions.

The operator console stands in for a trusted delivery channel in the local demo. It requires local filesystem and database access. The public form always gives the same response for valid email addresses, and the database keeps only the issued code’s hash.

## Directory overview

| Path | Contents |
| --- | --- |
| `public/` | Web document root with customer, provider, admin, account, and static asset pages |
| `includes/` | Database and session setup, validation, permissions, and shared application logic |
| `config/` | Local database connection settings |
| `scripts/reset-code.php` | Local password recovery operator command |
| `storage/profiles/` | Private, randomly named JPEGs, created on first upload |
| `storage/provider-images/` | Re-encoded company logos and vehicle photos, served through a PHP endpoint |
| `storage/provider-documents/` | Private legal documents, STNK, and KIR, served as authenticated downloads |
| `database.sql` | Database setup and fictional seed data |
| `migrations/provider-verification.sql` | One-time schema update for the original database |

## Tests

The fresh schema and one-time migration passed on XAMPP Apache 2.4.58, PHP 8.2.12, and MariaDB 10.4.32 using an isolated database. The migration preserved an existing paid booking and its seat. All 15 verification security cases passed, including valid uploads, disguised executables, oversize limits, cross-provider access, invalid CSRF, forged approval, and direct checkout attempts against an unverified provider’s schedule.

Regression checks covered registration and login, profile updates and photos, provider CRUD, admin monitoring, search through e-ticket issuance, and logout. Concurrent requests for the same seat produced one reservation. Chromium verified the provider upload and submission forms, admin download and approval, schedule publication, and customer booking. Standalone MySQL 8 runtime testing remains pending.
