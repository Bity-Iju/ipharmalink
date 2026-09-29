# ipharmalink
iPharmaLink is a digital pharmacy marketplace connecting customers with trusted pharmacies. Customers can easily find, order, pay for, and receive healthcare products, while pharmacies manage products, inventory, orders, payments, and deliveries through one secure platform.

# iPharmaLink — B2B PHARMACEUTICAL WHOLESALE & PHARMACY MARKETPLACE

Build a professional, secure, scalable **B2B pharmaceutical wholesale marketplace** called **iPharmaLink**.

iPharmaLink connects **large/wholesale pharmacies, pharmaceutical distributors, and suppliers** with **smaller retail/community pharmacies** that purchase medicines and healthcare products in bulk and subsequently sell them to their own end customers.

The primary business model is:

**Wholesale Pharmacy / Supplier → Retail Pharmacy → End Customer**

The platform should NOT be designed primarily as a conventional pharmacy-to-consumer marketplace.

The main purpose is to digitize pharmaceutical wholesale purchasing, allowing retail pharmacies to discover suppliers, compare wholesale products and prices, place bulk orders, make payments, track deliveries, manage their inventory, and subsequently serve their own customers.

---

# 1. CORE BUSINESS MODEL

iPharmaLink should support three major business participants:

### 1. Wholesale Pharmacy / Supplier

Large pharmacies, distributors, pharmaceutical wholesalers, manufacturers, or approved suppliers can:

* Register
* Submit business/license information
* Get verified by administrators
* Create their supplier storefront
* Upload pharmaceutical products
* Define wholesale prices
* Define minimum order quantities
* Define packaging units
* Manage stock
* Manage batches
* Manage expiry dates
* Receive wholesale orders
* Prepare orders
* Assign deliveries
* Track payments
* Manage invoices
* View sales reports

---

### 2. Retail Pharmacy / Buyer

Small, medium, community and retail pharmacies register as buyers.

They can:

* Browse wholesale suppliers
* Search products
* Compare prices
* View available stock
* View minimum order quantities
* Purchase by carton
* Purchase by pack
* Purchase by box
* Purchase by case
* Purchase by unit where permitted
* Add products to wholesale cart
* Place bulk orders
* Make payments
* Track deliveries
* Receive goods
* Manage inventory
* Record stock received
* Monitor expiry dates
* View purchase history
* Manage their own retail products/prices
* Manage their own customers

The retail pharmacy is **NOT the final consumer**.

---

### 3. End Customer

End customers belong to/use a retail pharmacy.

They can:

* View products offered by their local/retail pharmacy
* Search products
* Place retail orders
* Pay the retail pharmacy
* Select delivery or pickup
* Track orders
* Receive products
* Leave reviews where enabled

The end customer purchases from the **retail pharmacy**, not directly from the wholesale supplier.

---

# 2. PLATFORM FLOW

The core platform flow should be:

```text
WHOLESALE SUPPLIER
       │
       │ Bulk Products
       │ Wholesale Pricing
       ▼
iPharmaLink Marketplace
       │
       │ Cartons / Packs / Boxes / Cases
       ▼
RETAIL PHARMACY
       │
       │ Manages Inventory
       │ Sets Retail Prices
       ▼
END CUSTOMER
       │
       │ Retail Purchase
       ▼
Delivery / Pickup
```

This distinction must be reflected throughout the database, UI, dashboards, orders, payments and reports.

---

# 3. USER ROLES

Implement role-based access control.

Primary roles:

### Super Administrator

Controls the entire platform.

### Wholesale Supplier

Sells pharmaceutical products in bulk to retail pharmacies.

### Retail Pharmacy

Purchases wholesale products and sells them to end customers.

### Pharmacy Staff

Employees working for a wholesale or retail pharmacy.

### Delivery Personnel

Handles supplier-to-retail-pharmacy deliveries and optionally retail customer deliveries.

### End Customer

Purchases products from retail pharmacies.

Optional future roles:

* Manufacturer
* Pharmaceutical Distributor
* Sales Representative
* Accountant
* Procurement Officer
* Warehouse Manager

Build the permission system so additional roles can easily be added later.

---

# 4. SUPPLIER / WHOLESALE PHARMACY REGISTRATION

Create a professional supplier registration system.

Fields:

* Business name
* Pharmacy name
* Business registration number
* Pharmacy license number
* Pharmacist information
* Contact person
* Email
* Phone
* Logo
* Business address
* State
* City
* Country
* Warehouse address
* Delivery areas
* Operating hours
* Business description
* License/document uploads
* Bank details
* Tax information where applicable

Supplier status:

```text
Pending Verification
Under Review
Approved
Rejected
Suspended
Deactivated
```

Only approved suppliers should be able to sell.

---

# 5. RETAIL PHARMACY REGISTRATION

Retail pharmacies should register separately as buyers.

Fields:

* Pharmacy name
* Owner/business name
* Registration information
* Pharmacy license
* Pharmacist information
* Phone
* Email
* Logo
* Address
* State
* City
* Delivery address
* GPS/location
* Business hours
* Required documents

After registration:

```text
Pending Verification
        ↓
Administrator Review
        ↓
Approved Retail Pharmacy
```

Only approved retail pharmacies should be allowed to purchase regulated pharmaceutical products.

---

# 6. WHOLESALE SUPPLIER DASHBOARD

Create a complete professional supplier dashboard.

Dashboard statistics:

* Total products
* Active products
* Low stock
* Out of stock
* Expiring products
* Today's orders
* Pending orders
* Processing orders
* Ready orders
* Dispatched orders
* Delivered orders
* Cancelled orders
* Today's sales
* Monthly sales
* Total sales
* Outstanding payments
* Pending payouts

Charts:

* Sales by day
* Sales by month
* Top-selling products
* Order volume
* Revenue
* Inventory movement

---

# 7. SUPPLIER PRODUCT MANAGEMENT

Suppliers should be able to create wholesale product listings.

Product fields:

* Product name
* Generic name
* Brand
* Category
* Subcategory
* Product image
* Multiple images
* Manufacturer
* Active ingredient
* Strength
* Dosage form
* Pack size
* SKU
* Barcode
* Batch number
* Manufacturing date
* Expiry date
* Stock quantity
* Wholesale price
* Bulk price
* Minimum order quantity
* Maximum order quantity
* Units per pack
* Packs per carton
* Cartons per case
* Prescription requirement
* Product classification
* Product status

---

# 8. WHOLESALE PACKAGING / UNIT SYSTEM

This is one of the most important features.

Products may be sold in:

* Unit
* Strip
* Pack
* Box
* Carton
* Case
* Bundle

For example:

```text
Paracetamol 500mg

1 strip = 10 tablets
1 pack = 10 strips
1 carton = 20 packs
```

Or:

```text
Product:
Amoxicillin 500mg

1 box = 10 capsules
1 carton = 50 boxes
Wholesale MOQ = 5 cartons
```

The supplier should be able to define these relationships.

The system must automatically calculate:

* Units
* Packs
* Cartons
* Cases
* Total quantity
* Wholesale price

Do not hard-code packaging units.

---

# 9. WHOLESALE PRICING

Support multiple pricing levels.

Example:

```text
1–4 cartons       ₦X
5–9 cartons       ₦Y
10–49 cartons     ₦Z
50+ cartons       ₦A
```

The supplier can configure:

* Standard wholesale price
* Bulk price
* Distributor price
* Special buyer price
* Promotional price

Retail pharmacies should see the appropriate price based on quantity and eligibility.

---

# 10. MINIMUM ORDER QUANTITY

Every wholesale product can have:

* Minimum order quantity
* Maximum order quantity
* Order increment

Example:

```text
MOQ: 5 cartons
Order increment: 5 cartons
```

The customer cannot order:

```text
1 carton
2 cartons
3 cartons
```

if the supplier requires 5-carton increments.

The backend must enforce this rule, not only JavaScript.

---

# 11. WHOLESALE PRODUCT SEARCH

Retail pharmacies should be able to search the entire wholesale marketplace.

Search by:

* Product name
* Generic name
* Brand
* Manufacturer
* SKU
* Barcode
* Supplier
* Category

Filters:

* Price
* Supplier
* Location
* Availability
* MOQ
* Packaging
* Brand
* Manufacturer
* Prescription status
* Delivery availability

Sorting:

* Price
* Newest
* Popularity
* Supplier
* Stock availability

---

# 12. SUPPLIER MARKETPLACE

Create:

```text
/wholesalers
```

Retail pharmacies can browse approved suppliers.

Supplier profile:

* Logo
* Business name
* Location
* Verification status
* Product count
* Categories
* Minimum order
* Delivery areas
* Delivery time
* Rating
* Reviews
* Contact information

Supplier storefront:

```text
/wholesaler/{slug}
```

---

# 13. WHOLESALE SHOPPING CART

Retail pharmacies can add products from suppliers to a wholesale cart.

The cart must correctly handle multiple suppliers.

Example:

```text
Wholesale Cart

Supplier A
 ├── Product 1
 ├── Product 2
 └── Product 3

Supplier B
 ├── Product 4
 └── Product 5
```

At checkout, create separate supplier sub-orders.

Example:

```text
Parent Purchase #10001

Supplier A
  └── PO-10001-A

Supplier B
  └── PO-10001-B
```

Each supplier only sees its own purchase order.

---

# 14. PURCHASE ORDER SYSTEM

Create a professional B2B Purchase Order system.

Retail pharmacy can:

* Create purchase order
* Submit purchase order
* Cancel eligible PO
* View PO
* Download PO
* Print PO
* Track PO

Supplier can:

* Receive PO
* Accept PO
* Reject PO
* Modify availability where allowed
* Confirm quantities
* Prepare order
* Generate invoice
* Mark ready
* Dispatch
* Mark delivered

---

# 15. WHOLESALE CHECKOUT

Checkout should include:

### Buyer

* Retail pharmacy name
* Contact person
* Phone
* Email

### Delivery

* Delivery address
* Warehouse/store address
* Delivery instructions

### Order

* Supplier
* Products
* Quantity
* Packaging
* Unit price
* Subtotal
* Discount
* Delivery fee
* Tax
* Total

### Payment

Support:

* Online payment
* Bank transfer
* Wallet
* Credit terms where approved
* Paystack
* Flutterwave

---

# 16. B2B PAYMENT SYSTEM

Implement a robust payment architecture.

For Nigeria, integrate-ready support should include:

* Paystack
* Flutterwave
* Bank transfer
* Manual payment verification

Payment statuses:

```text
Pending
Processing
Successful
Failed
Partially Paid
Overdue
Refunded
Cancelled
```

For bank transfer:

* Generate payment reference
* Display payment instructions
* Allow payment proof upload where needed
* Admin/supplier can verify payment

Never trust client-side payment confirmation.

Use gateway webhook/server-side verification.

---

# 17. CREDIT / PAYMENT TERMS

Because this is a B2B wholesale system, support optional credit terms.

A supplier may approve a retail pharmacy for:

```text
Cash Only
7 Days
14 Days
30 Days
60 Days
Custom
```

Credit settings:

* Credit limit
* Current balance
* Outstanding amount
* Available credit
* Due date
* Payment history

The supplier should be able to suspend further purchases if the buyer exceeds the credit limit.

This feature should be configurable and disabled unless explicitly enabled by the administrator/supplier.

---

# 18. RETAIL PHARMACY DASHBOARD

Build a complete retail pharmacy dashboard.

Statistics:

* Total purchases
* Pending purchases
* Orders in transit
* Delivered purchases
* Inventory value
* Low-stock products
* Expiring products
* Retail sales
* Today's sales
* Monthly sales
* Outstanding supplier payments
* Customer orders

Charts:

* Purchase trends
* Retail sales
* Top-selling products
* Inventory
* Supplier spending

---

# 19. RETAIL PHARMACY INVENTORY

The retail pharmacy must have its own independent inventory.

When a wholesale order is delivered:

```text
Supplier Stock
       ↓
Wholesale Order
       ↓
Retail Pharmacy Receives Goods
       ↓
Retail Pharmacy Inventory INCREASES
```

When a retail customer buys:

```text
Retail Customer Order
       ↓
Retail Pharmacy Inventory DECREASES
```

Track:

* Opening stock
* Stock received
* Stock sold
* Stock adjustment
* Damaged stock
* Returned stock
* Expired stock
* Current stock
* Batch
* Expiry date

---

# 20. RETAIL PHARMACY PRODUCT CATALOG

A retail pharmacy should NOT automatically have to sell products at the supplier's wholesale price.

After purchasing stock, the retail pharmacy can configure its retail listing.

Fields:

* Retail product name
* Product description
* Retail price
* Discount price
* Available quantity
* Visibility
* Featured status
* Customer purchase limits

The system should maintain a relationship between:

```text
Wholesale Product
       ↓
Retail Pharmacy Inventory
       ↓
Retail Product Listing
```

---

# 21. RETAIL CUSTOMER STOREFRONT

Each retail pharmacy should have its own online storefront.

Example:

```text
/pharmacy/abc-community-pharmacy
```

Customers can:

* Browse pharmacy products
* Search
* Add to cart
* Checkout
* Pay
* Choose delivery
* Choose pickup
* Track order

The end customer should clearly see which retail pharmacy is fulfilling the order.

---

# 22. END CUSTOMER ORDER FLOW

The end-user process should be:

```text
Customer
   ↓
Select Retail Pharmacy
   ↓
Browse Products
   ↓
Add to Cart
   ↓
Checkout
   ↓
Pay Retail Pharmacy
   ↓
Retail Pharmacy Receives Order
   ↓
Prepare Order
   ↓
Delivery / Pickup
   ↓
Customer Receives Order
```

The wholesale supplier should not be involved in the end-customer order unless explicitly required for a future fulfillment model.

---

# 23. RETAIL ORDER MANAGEMENT

Retail pharmacy can:

* Accept order
* Reject order
* Prepare order
* Mark ready
* Assign delivery
* Dispatch
* Mark delivered
* Cancel
* Refund

Statuses:

```text
Pending
Paid
Accepted
Processing
Ready
Out for Delivery
Delivered
Cancelled
Refunded
```

---

# 24. DELIVERY SYSTEM

Support two separate delivery flows.

### A. Supplier → Retail Pharmacy

Wholesale delivery.

### B. Retail Pharmacy → End Customer

Retail delivery.

Do not mix these two order types.

Delivery records should include:

* Delivery number
* Order
* Sender
* Receiver
* Address
* Phone
* Delivery personnel
* Status
* Proof of delivery
* OTP
* Timestamp
* Delivery note

---

# 25. DELIVERY PERSONNEL DASHBOARD

Create a dedicated dashboard.

Features:

* Assigned deliveries
* Today's deliveries
* Pending deliveries
* Completed deliveries
* Failed deliveries
* Delivery history

Delivery personnel can:

* View destination
* Call customer
* Update status
* Enter delivery OTP
* Add delivery note
* Upload proof of delivery
* Mark delivered

---

# 26. SUPPLIER → RETAIL DELIVERY TRACKING

Retail pharmacy should be able to track:

```text
Order Confirmed
     ↓
Preparing
     ↓
Ready for Dispatch
     ↓
Dispatched
     ↓
In Transit
     ↓
Delivered
```

The retail pharmacy should receive notifications at every important stage.

---

# 27. END CUSTOMER FEATURES

Create:

* Registration
* Login
* Profile
* Address book
* Pharmacy selection
* Product search
* Cart
* Checkout
* Payment
* Orders
* Order tracking
* Wishlist
* Reviews
* Notifications
* Receipts
* Reorder

---

# 28. ADMIN DASHBOARD

Create a powerful Super Admin dashboard overseeing the entire ecosystem.

Dashboard:

* Total suppliers
* Total retail pharmacies
* Total customers
* Total staff
* Total products
* Wholesale orders
* Retail orders
* Total wholesale sales
* Total retail sales
* Platform revenue
* Commissions
* Pending payouts
* Pending verifications
* Pending payments
* Pending deliveries

Charts:

* Supplier registrations
* Retail pharmacy registrations
* Wholesale purchases
* Retail purchases
* Revenue
* Product sales
* Delivery performance

---

# 29. ADMIN SUPPLIER MANAGEMENT

Admin can:

* View suppliers
* Approve suppliers
* Reject suppliers
* Suspend suppliers
* Verify documents
* View supplier products
* View supplier inventory
* View supplier orders
* View supplier sales
* View supplier payments
* Configure commission
* View supplier activity
* View supplier ratings

---

# 30. ADMIN RETAIL PHARMACY MANAGEMENT

Admin can:

* View retail pharmacies
* Approve/reject
* Suspend/activate
* View purchases
* View inventory
* View retail products
* View customer orders
* View payments
* View sales
* View staff
* View activity

---

# 31. ADMIN CUSTOMER MANAGEMENT

Admin can:

* View customers
* Search customers
* View orders
* View pharmacy relationship
* Suspend/activate accounts
* View payment history
* View complaints

---

# 32. ADMIN PRODUCT MANAGEMENT

Admin can manage:

* Global categories
* Subcategories
* Brands
* Manufacturers
* Product classifications
* Pharmaceutical products
* Product approvals
* Restricted products
* Prescription-required products

---

# 33. PHARMACEUTICAL COMPLIANCE

Because this platform deals with medicines, implement configurable compliance controls.

Product classifications:

```text
OTC
Prescription Required
Controlled/Restricted
Medical Device
Supplement
Cosmetic
Other
```

Support:

* License verification
* Pharmacy verification
* Product approval
* Prescription upload
* Prescription review
* Restricted-product rules
* Batch tracking
* Expiry tracking
* Recall management
* Audit trail

The exact rules should be configurable according to the jurisdiction where iPharmaLink operates.

---

# 34. BATCH & EXPIRY MANAGEMENT

Every pharmaceutical batch should support:

* Batch number
* Manufacturing date
* Expiry date
* Quantity
* Supplier
* Purchase date
* Cost price

Implement expiry alerts:

```text
Expired
Expiring within 30 days
Expiring within 60 days
Expiring within 90 days
```

Support configurable FEFO:

**First Expiry, First Out**

where appropriate.

---

# 35. PRODUCT RECALL SYSTEM

Admin/suppliers should be able to flag a batch as recalled.

Workflow:

```text
Supplier identifies recalled batch
        ↓
Admin/Supplier marks batch recalled
        ↓
Affected retail pharmacies notified
        ↓
Affected inventory identified
        ↓
Product removed from sale
        ↓
Return/replacement workflow
```

---

# 36. RETURNS & REFUNDS

Wholesale returns:

```text
Retail Pharmacy
      ↓
Return Request
      ↓
Supplier Review
      ↓
Approved / Rejected
      ↓
Goods Returned
      ↓
Refund / Replacement
```

Retail returns:

```text
End Customer
      ↓
Return Request
      ↓
Retail Pharmacy
      ↓
Review
      ↓
Refund / Replacement
```

Create separate return systems for wholesale and retail orders.

---

# 37. COMMISSION SYSTEM

iPharmaLink may earn commission from transactions.

Support:

### Supplier Commission

Platform takes a percentage from wholesale sales.

### Retail Pharmacy Commission

Platform takes a percentage from retail sales.

Allow:

* Global commission
* Supplier-specific commission
* Pharmacy-specific commission
* Category-specific commission
* Fixed fee
* Percentage fee

---

# 38. WALLET & PAYOUT SYSTEM

Suppliers and retail pharmacies should have financial dashboards.

Show:

* Gross sales
* Platform commission
* Refunds
* Net earnings
* Available balance
* Pending balance
* Payouts

Admin controls:

* Payout approval
* Payout schedule
* Minimum payout
* Bank account
* Payout history

---

# 39. INVOICING

Generate professional:

### Wholesale Invoice

```text
iPharmaLink
Supplier
Retail Pharmacy
Invoice Number
Purchase Order
Products
Quantity
Wholesale Price
Discount
Tax
Delivery
Total
Payment Status
```

### Retail Receipt

```text
Retail Pharmacy
Customer
Order Number
Products
Quantity
Retail Price
Delivery
Discount
Total
Payment
```

Allow:

* Print
* PDF
* Download
* Email

---

# 40. REPORTING

Supplier reports:

* Sales
* Wholesale orders
* Revenue
* Top products
* Inventory
* Expiry
* Customers/buyers
* Outstanding invoices
* Payments

Retail pharmacy reports:

* Purchases
* Supplier spending
* Inventory
* Retail sales
* Gross profit
* Top products
* Expiry
* Customer orders
* Outstanding supplier balances

Admin reports:

* Platform sales
* Wholesale GMV
* Retail GMV
* Commissions
* Suppliers
* Retail pharmacies
* Customers
* Products
* Orders
* Payments
* Delivery
* Refunds
* Inventory

Export:

* CSV
* Excel
* PDF

---

# 41. RETAIL PROFIT CALCULATION

For retail pharmacies, calculate:

```text
Wholesale Cost
+
Additional Costs
=
Cost of Goods

Retail Selling Price
-
Cost of Goods
=
Gross Profit
```

Dashboard should display:

* Purchase cost
* Selling price
* Gross profit
* Profit margin

Do not expose a retail pharmacy's internal profit margin to suppliers or other pharmacies.

---

# 42. NOTIFICATION SYSTEM

Create notifications for:

### Supplier

* New wholesale order
* Payment received
* Low stock
* Expiry alert
* Return request
* Payment due

### Retail Pharmacy

* Supplier order accepted
* Supplier order dispatched
* Supplier order delivered
* Payment reminder
* Low inventory
* Product expiry
* Customer order
* Customer payment

### Customer

* Order placed
* Payment confirmed
* Order accepted
* Order ready
* Order dispatched
* Order delivered
* Refund

---

# 43. ADMIN CMS

Admin should manage:

* Homepage
* Banners
* About Us
* Contact
* FAQs
* Terms
* Privacy
* Supplier terms
* Retail pharmacy terms
* Refund policy
* Delivery policy
* Help center

---

# 44. DATABASE ARCHITECTURE

Create a normalized MySQL/MariaDB database.

Core tables should include:

```text
users
roles
permissions
user_roles

suppliers
supplier_documents
supplier_settings
supplier_staff

retail_pharmacies
retail_pharmacy_documents
retail_pharmacy_settings
retail_pharmacy_staff

customers

products
product_images
product_categories
categories
subcategories
brands
manufacturers

product_batches
inventory
inventory_movements

wholesale_prices
price_tiers
packaging_units

carts
cart_items

purchase_orders
purchase_order_items
supplier_orders
supplier_order_status_history

retail_products
retail_product_prices

retail_orders
retail_order_items
retail_order_status_history

payments
payment_transactions
invoices
refunds

delivery_addresses
deliveries
delivery_status_history

prescriptions
prescription_reviews

returns
return_items

reviews
wishlists
wishlist_items

notifications

wallets
commissions
payouts

credit_accounts
credit_transactions

coupons
coupon_usages

product_recalls
recall_items

pages
banners
faqs

audit_logs
platform_settings
```

Use proper foreign keys, indexes, constraints and timestamps.

---

# 45. APPLICATION ARCHITECTURE

Use clean modular PHP architecture.

Recommended structure:

```text
ipharmalink/
│
├── admin/
├── supplier/
├── pharmacy/
├── customer/
├── delivery/
├── auth/
├── api/
│
├── config/
├── database/
├── includes/
├── middleware/
├── models/
├── controllers/
├── services/
├── components/
├── templates/
│
├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/
│
├── storage/
├── index.php
├── .htaccess
├── .env.example
└── README.md
```

Use:

* PHP 8+
* PDO
* MySQL/MariaDB
* Bootstrap 5
* JavaScript
* AJAX/fetch
* HTML5
* CSS3

---

# 46. SECURITY

Implement:

* PDO prepared statements
* Password hashing
* CSRF protection
* XSS protection
* SQL injection prevention
* Session security
* Role-based access control
* Permission-based authorization
* Secure file uploads
* MIME validation
* Image validation
* Rate limiting
* Login protection
* Secure password reset
* Payment verification
* Webhook verification
* Audit logging

Never trust:

* Client-side price
* Client-side stock
* Client-side role
* Client-side payment status
* Client-side order total

Recalculate everything server-side.

---

# 47. REQUIRED DASHBOARDS

Build all of the following dashboards as fully functional interfaces.

## Super Admin Dashboard

```text
Dashboard
Suppliers
Retail Pharmacies
Customers
Staff
Products
Categories
Orders
Payments
Commissions
Payouts
Deliveries
Returns
Refunds
Reviews
Prescriptions
Recalls
Reports
CMS
Notifications
Audit Logs
Settings
```

## Wholesale Supplier Dashboard

```text
Dashboard
Products
Inventory
Batches
Orders
Purchase Orders
Invoices
Payments
Returns
Deliveries
Buyers
Staff
Reports
Reviews
Notifications
Settings
```

## Retail Pharmacy Dashboard

```text
Dashboard
Wholesale Marketplace
Suppliers
Purchase Orders
Purchases
Inventory
Products
Retail Prices
Customers
Retail Orders
Deliveries
Payments
Credit Account
Invoices
Returns
Reports
Staff
Notifications
Settings
```

## Delivery Dashboard

```text
Dashboard
Assigned Deliveries
Active Deliveries
Completed
Failed
History
Profile
```

## End Customer Dashboard

```text
Dashboard
My Pharmacy
Products
Cart
Orders
Wishlist
Addresses
Payments
Reviews
Notifications
Profile
```

---

# 48. IMPORTANT SEPARATION OF DATA

The system must enforce strict data separation.

### Supplier

Can see:

* Own products
* Own inventory
* Own buyers
* Own wholesale orders
* Own revenue

Cannot see:

* Other supplier information
* Other suppliers' customers
* Retail pharmacy private profit
* Other suppliers' prices unless marketplace rules allow it

### Retail Pharmacy

Can see:

* Own purchases
* Own suppliers
* Own inventory
* Own retail products
* Own customers
* Own retail orders
* Own revenue
* Own profit

Cannot see:

* Other pharmacies' customers
* Other pharmacies' private sales
* Supplier private information not intended for buyers

### Customer

Can see:

* Selected pharmacy
* Available retail products
* Own orders
* Own payments
* Own addresses

Cannot access:

* Wholesale prices
* Supplier dashboards
* Retail pharmacy internal inventory/cost
* Other customers' information

---

# 49. REQUIRED PUBLIC PAGES

```text
/
Home

/about
About iPharmaLink

/how-it-works
How It Works

/suppliers
Wholesale Suppliers

/supplier/{slug}
Supplier Store

/pharmacies
Retail Pharmacies

/pharmacy/{slug}
Retail Pharmacy Store

/products
Products

/product/{slug}
Product Details

/categories
Categories

/search
Search

/contact
Contact

/faq
FAQ

/terms
Terms

/privacy
Privacy

/refund-policy
Refund Policy

/delivery-policy
Delivery Policy
```

---

# 50. SUPPLIER PAGES

```text
/supplier/register
/supplier/login
/supplier/dashboard

/supplier/profile
/supplier/settings

/supplier/products
/supplier/products/create
/supplier/products/edit/{id}

 /supplier/inventory
/supplier/batches

/supplier/orders
/supplier/orders/{id}

/supplier/invoices
/supplier/payments
/supplier/returns
/supplier/deliveries

/supplier/buyers
/supplier/staff
/supplier/reports
/supplier/notifications
```

---

# 51. RETAIL PHARMACY PAGES

```text
/pharmacy/register
/pharmacy/login
/pharmacy/dashboard

/pharmacy/marketplace
/pharmacy/suppliers

/pharmacy/products
/pharmacy/products/create
/pharmacy/products/edit/{id}

/pharmacy/inventory
/pharmacy/purchases
/pharmacy/purchase-orders
/pharmacy/purchase-orders/{id}

/pharmacy/customers

/pharmacy/orders
/pharmacy/orders/{id}

/pharmacy/deliveries
/pharmacy/payments
/pharmacy/invoices
/pharmacy/returns

/pharmacy/credit
/pharmacy/reports
/pharmacy/staff
/pharmacy/settings
```

---

# 52. CUSTOMER PAGES

```text
/login
/register
/forgot-password

/account
/account/profile
/account/orders
/account/orders/{id}
/account/addresses
/account/wishlist
/account/payments
/account/reviews
/account/notifications

/cart
/checkout
/payment
/payment/success
/payment/failed
```

---

# 53. ADMIN PAGES

```text
/admin/login
/admin/dashboard

/admin/suppliers
/admin/suppliers/pending
/admin/suppliers/{id}

/admin/pharmacies
/admin/pharmacies/pending
/admin/pharmacies/{id}

/admin/customers
/admin/staff
/admin/delivery-personnel

/admin/products
/admin/categories
/admin/subcategories
/admin/brands
/admin/manufacturers

/admin/wholesale-orders
/admin/retail-orders

/admin/payments
/admin/invoices
/admin/refunds
/admin/returns

/admin/commissions
/admin/wallets
/admin/payouts
/admin/credit

/admin/deliveries
/admin/delivery-zones

/admin/prescriptions
/admin/recalls
/admin/reviews

/admin/reports
/admin/coupons

/admin/pages
/admin/banners
/admin/faqs

/admin/notifications
/admin/audit-logs

/admin/settings
/admin/payment-settings
/admin/email-settings
/admin/security-settings
```

---

# 54. HOMEPAGE DESIGN

The homepage should communicate the B2B model immediately.

Hero:

**"Connect. Purchase. Stock. Grow."**

Supporting message:

**"iPharmaLink connects retail pharmacies with trusted pharmaceutical wholesalers, making bulk purchasing faster, easier and more transparent."**

Primary buttons:

```text
Find Wholesale Products
Register Your Pharmacy
Become a Supplier
```

Sections:

* How iPharmaLink works
* Verified suppliers
* Popular wholesale products
* Featured pharmacies
* Categories
* Bulk purchasing benefits
* Delivery
* Secure payments
* Pharmacy growth tools
* Testimonials
* FAQ

---

# 55. RESPONSIVE DESIGN

Use Bootstrap 5 and custom CSS.

The system must work on:

* Desktop
* Laptop
* Tablet
* Android
* iPhone

Dashboards must have:

* Responsive sidebar
* Mobile navigation
* Responsive tables
* Cards
* Charts
* Modals
* Toasts
* Filters
* Search
* Pagination

---

# 56. PERFORMANCE

Implement:

* Pagination
* Database indexing
* Lazy image loading
* Image compression
* Efficient SQL queries
* AJAX where appropriate
* Server-side filtering
* Server-side pagination
* Caching-ready architecture

Avoid loading thousands of records into the browser.

---

# 57. AUDIT LOGGING

Record important activities:

```text
User
Role
Action
Entity
Entity ID
IP Address
Timestamp
Old Value
New Value
```

Examples:

```text
Supplier changed wholesale price
Retail pharmacy placed purchase order
Admin approved supplier
Pharmacy received stock
Customer placed retail order
Payment verified
Product batch recalled
Inventory adjusted
```

---

# 58. ANALYTICS

Create dashboards showing:

### Platform

* GMV
* Wholesale GMV
* Retail GMV
* Commission
* Active suppliers
* Active pharmacies
* Active customers

### Supplier

* Sales
* Orders
* Products
* Buyers
* Revenue
* Top products

### Retail Pharmacy

* Purchases
* Retail sales
* Inventory
* Profit
* Customers
* Top products

---

# 59. INSTALLATION

Provide:

```text
.env.example
database.sql
seed.sql
README.md
```

README should explain:

1. PHP requirements
2. MySQL setup
3. Database installation
4. Environment configuration
5. Payment gateway configuration
6. SMTP configuration
7. File permissions
8. Admin creation
9. Supplier registration
10. Retail pharmacy registration
11. Testing the wholesale order process
12. Testing the retail order process
13. Production deployment

---

# 60. TESTING

Create test scenarios for:

### Supplier

* Registration
* Verification
* Product creation
* Stock management
* Wholesale pricing
* Order acceptance
* Delivery

### Retail Pharmacy

* Registration
* Supplier browsing
* Wholesale purchase
* Payment
* Receiving stock
* Inventory update
* Retail pricing
* Customer order

### Customer

* Registration
* Pharmacy selection
* Product purchase
* Payment
* Delivery
* Review

### Admin

* Supplier approval
* Pharmacy approval
* Product management
* Order management
* Payment monitoring
* Commission
* Reports

---

# 61. CRITICAL BUSINESS RULE

The system must clearly distinguish between:

### WHOLESALE TRANSACTION

```text
Supplier
     ↓
Retail Pharmacy
```

and:

### RETAIL TRANSACTION

```text
Retail Pharmacy
     ↓
End Customer
```

These must have separate:

* Orders
* Pricing
* Inventory movements
* Invoices
* Payments
* Delivery records
* Reports
* Dashboards
* Status workflows

---

# 62. FINAL DEVELOPMENT REQUIREMENT

Do not build this as a simple pharmacy website.

Build **iPharmaLink as a complete multi-vendor B2B pharmaceutical commerce ecosystem**.

The primary objective is to allow large/wholesale pharmacies and pharmaceutical suppliers to digitize their wholesale sales while allowing smaller/community pharmacies to conveniently purchase medicines in bulk and manage their own inventory and retail sales.

The complete ecosystem should be:

```text
                    iPharmaLink
                         │
        ┌────────────────┴────────────────┐
        │                                 │
 Wholesale Suppliers                Retail Pharmacies
        │                                 │
        │ Bulk Sales                      │ Retail Sales
        ▼                                 ▼
 Cartons / Cases / Packs             End Customers
        │                                 │
        └───────────────┬─────────────────┘
                        │
                 Delivery Network
                        │
                 Payment System
                        │
                 Admin Oversight
```

Every dashboard, database table, payment flow, inventory process, order process and report must respect this architecture.

Build the system so that **all CRUD operations, authentication, permissions, database operations, orders, payments, inventory updates, delivery tracking and dashboards are functional**, not static mockups.

Use secure, maintainable PHP architecture with Bootstrap 5, HTML, CSS, JavaScript, MySQL/MariaDB and PDO.

Design the system so additional suppliers, pharmacies, products, customers, delivery personnel and transactions can be added without changing the core architecture.

The final product should be professional enough to serve as the foundation for a real-world **iPharmaLink B2B pharmaceutical marketplace**.
