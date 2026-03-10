# UKHSA Data Governance Portal – Prototype

## Overview
The **UKHSA Data Governance Portal – Prototype** is a web-based application designed to manage and automate the process of requesting and granting access to organisational datasets.

The system replaces a manual workflow that previously required users to submit access requests through web forms, export them into spreadsheets, and process them via scripts once per day. This prototype demonstrates how the entire process can be streamlined through a centralized web application with automated rules and role-based approvals.

This project is developed as a **proof-of-concept prototype** and does not connect to real UKHSA systems or datasets.

---

## Objectives
The main objectives of this project are:

- Provide a **centralized portal** for dataset access requests
- Automate approvals for **non-sensitive datasets**
- Route **sensitive dataset requests** to approvers for review
- Provide **role-based dashboards** for users, approvers, and administrators
- Maintain a **complete audit trail** of all actions
- Demonstrate **data governance and access control concepts**

---

## Key Features

### User Features
- Secure login system
- View available datasets through a dataset catalogue
- Submit access requests
- Specify access type and purpose
- View request history and status

### Approver Features
- View pending access requests
- Approve or reject requests
- Provide review comments
- Automatically grant permissions upon approval

### Admin Features
- Manage datasets and sensitivity classification
- Configure approval rules
- View system-wide access grants
- Monitor audit logs

### System Features
- Automatic approval for **non-sensitive datasets**
- Manual approval workflow for **sensitive datasets**
- Automatic permission expiry and revocation
- Complete **audit logging** for compliance and governance

---

## System Workflow

1. User logs into the portal.
2. User browses the dataset catalogue.
3. User submits an access request.
4. The system checks dataset sensitivity:
   - Non-sensitive → Auto-approved
   - Sensitive → Sent to approver dashboard
5. Approver reviews and approves/rejects request.
6. Approved requests create an active access grant.
7. All actions are recorded in the audit log.

---

## Technology Stack

**Frontend**
- HTML
- CSS
- JavaScript

**Backend**
- PHP

**Database**
- PostgreSQL

---

## Security Considerations

The prototype includes basic security features:

- Session-based authentication
- Password hashing
- Role-based access control (RBAC)
- Server-side request validation
- Restricted access to protected pages

---

## Limitations

This project is a **prototype** and does not include:

- Integration with real organisational databases
- Enterprise identity providers (e.g., Active Directory)
- Production-level security or deployment
- Real sensitive data

All users and datasets are **fictional and used for demonstration purposes only**.

---

## Expected Benefits

If implemented in a real environment, this system could provide:

- Faster dataset access for users
- Reduced manual processing
- Improved visibility of data access permissions
- Easier management of approval policies
- Stronger audit and compliance capabilities

---
