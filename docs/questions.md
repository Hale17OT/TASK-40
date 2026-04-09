## HarborBite Technical Clarifications: Architecture & Logic

(1) Promo Logic Resolution
- **Question:** Does the "Best Offer" calculation apply to the total bill or individual line items first?
- **My Understanding:** Calculating every possible combination of BOGO and percentage discounts can lead to $O(n!)$ complexity, which will lag on low-power tablet hardware.
- **Solution:** Implement a defined **Resolution Tree** where item-level discounts are calculated and "locked" first, followed by cart-level totals. This ensures predictable performance while still providing the best outcome for the guest.

(2) Zombie Order State
- **Question:** What is the UX and logic when an order becomes "stale" during a secure in-store payment capture?
- **My Understanding:** If a manager cancels an order while a guest is at the checkout screen, the guest's request must be rejected to prevent data mismatch.
- **Solution:** Automatically void any local payment intent on the tablet and route the order to an **"Abnormal Payment-State Repair"** queue for manual manager reconciliation.

(3) Offline Identity Sync
- **Question:** How are guest registrations and sessions synced across multiple physical tablets in an offline environment?
- **My Understanding:** Guests may register on one tablet but expect their account to be available on another during the same visit.
- **Solution:** Use the **local PostgreSQL server** as the central authority for real-time tablet-to-tablet sync; guest sessions remain active until an explicit "Settle" event occurs or a 4-hour inactivity sweep clears the local cache.

(4) Key Persistence Security
- **Question:** How do we secure encryption keys in an on-premise environment where the physical hardware is vulnerable to theft?
- **My Understanding:** Storing keys on the same disk as the encrypted medical or sensitive notes renders the encryption useless if the server is stolen.
- **Solution:** Require a **Physical "Key USB"** or a separate network-attached storage (NAS) to host the environment variables, ensuring the keys are not physically present on the primary database disk.

(5) HMAC Clock Drift
- **Question:** How can we enforce a 5-minute HMAC expiry window when local tablet clocks inevitably drift in an offline LAN?
- **My Understanding:** If a tablet's clock is out of sync with the server, valid requests will be rejected as "expired" immediately.
- **Solution:** Implement a **"Time-Sync Heartbeat"** where tablets periodically fetch the server's time to calculate a local offset, ensuring HMAC signatures use a unified timestamp despite hardware drift.


(6) Allergen Search Logic
- **Question:** Should "contains nuts" be treated as a negative filter or a highlighted attribute in the search results?
- **My Understanding:** Guests typically search for what they *can* eat, meaning "contains nuts" should act as an exclusion rather than a match.
- **Solution:** Implement this as a **Negative Filter** using a PostgreSQL `NOT EXISTS` clause to completely remove all items with the specified allergen tag from the guest's view.

(7) Tax Calculation Precision
- **Question:** Does the system support tax-inclusive pricing, and are there specific tax-exempt item categories?
- **My Understanding:** Tax rules vary by jurisdiction and food type (e.g., hot vs. cold items).
- **Solution:** Implement a dedicated **Tax-Rule Table** with foreign keys to item categories, allowing for flexible configuration of tax-inclusive or exclusive rules without hardcoding percentages.

(8) Manager Override Auditing
- **Question:** How do we track accountability when a Manager PIN is used to settle or cancel an order offline?
- **My Understanding:** A simple "action happened" log is insufficient for internal security and fraud prevention.
- **Solution:** Create a **Privilege Escalation Log** that captures the specific Manager ID associated with the PIN entry, creating a many-to-one relationship between orders and authorized overrides.

(9) Banned Word UX
- **Question:** What should the system suggest as a "refinement" when a user triggers a banned word or profanity filter?
- **My Understanding:** Suggesting linguistic variations of banned words is counter-productive and potentially offensive.
- **Solution:** When a banned word is detected, the UI will block the input and suggest **"Trending Menu Terms"** or neutral search categories instead of trying to refine the original query.

(10) RBAC Dashboards
- **Question:** Can roles like "Manager" or "Cashier" access overlapping views, such as the kitchen's preparation list?
- **My Understanding:** Roles should be rigid, but operational needs often require cross-functional visibility during peak hours.
- **Solution:** Implement a **Role-Based Access Control (RBAC) Matrix** that allows shared "Read-Only" views for Cashiers while restricting "Write" actions (like marking an order as ready) to Kitchen staff.

(11) Local CAPTCHA
- **Question:** How can we implement CAPTCHA for failed logins in a system without an external internet connection?
- **My Understanding:** Standard cloud-based solutions like reCAPTCHA will fail in an offline environment.
- **Solution:** Utilize a **Local Image-Generation Library** (such as `Gregwar/Captcha`) to produce math-based or distorted-text challenges directly on the on-premise server.

(12) Analytics Performance
- **Question:** How do we prevent GMV and conversion queries from lagging as the event log grows to millions of rows over time?
- **My Understanding:** Calculating real-time analytics from raw event logs is too computationally expensive for a local server.
- **Solution:** Implement **Materialized Views** or a "Daily Summary" table that is populated via a background Laravel scheduled task during off-peak hours.

(13) Settlement Fraud Prevention
- **Question:** How do we prevent internal fraud when a manager manually marks an "ambiguous" payment as settled?
- **My Understanding:** Staff could potentially pocket cash and use an override to hide the missing funds.
- **Solution:** Require a **Mandatory Reason Code** and a physical receipt ID (or photo upload) for all "Ambiguous Settlement" overrides to maintain a high-integrity audit trail.

(14) Concurrency Lock Strategy
- **Question:** Who wins when a Kitchen staff member marks an item as "Started" while a Cashier simultaneously tries to delete it?
- **My Understanding:** We need a priority matrix to prevent food waste and data conflicts between the front and back of house.
- **Solution:** Implement a **Priority Lock**; once an item enters the "In Preparation" state, it is locked from deletion by the Guest or Cashier, requiring a Manager override to cancel.