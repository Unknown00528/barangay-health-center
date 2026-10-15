# BHW Assist — Use Case & Data Flow Diagrams

**System:** BHW Assist — *Barangay Health Worker Management System*
**Stack:** PHP (procedural, MySQLi) + MySQL (`bhw_assist`) + Bootstrap 5 + Chart.js
**Authentication:** Session-based, CSRF-protected, two roles — **BHW** and **Admin**

---

## 1. Actor Definitions

| Actor | Description | Access |
|-------|-------------|--------|
| **BHW Worker** | Frontline Barangay Health Worker who records patient data and health services | All core modules (Patients, Consultations, Home Visits, Vaccinations, Medicines, Follow-ups, Dashboard, Reports) |
| **Administrator** | System administrator / health officer | All BHW capabilities **plus** BHW Worker account management and Login History audit |

> Both actors must pass `require_login()` (session guard) before reaching any module. Admin-only pages additionally call `require_admin()` which returns HTTP 403 on failure.

---

## 2. Use Case Diagram

```mermaid
flowchart TB
    %% ===== Actors =====
    BHW(["👤 BHW Worker"])
    ADMIN(["👤 Administrator"])

    %% ===== System Boundary =====
    subgraph SYSTEM["BHW ASSIST — Barangay Health Worker Management System"]
        direction TB

        subgraph AUTH["🔐 Authentication"]
            UC1(["UC1 Login / Logout"])
        end

        subgraph CORE["Core Health Operations"]
            UC2(["UC2 View Dashboard<br/>& Analytics"])
            UC3(["UC3 Manage Patients<br/>(Add / Edit / Delete / Search)"])
            UC4(["UC4 Import Patient List<br/>(CSV) + Download Template"])
            UC5(["UC5 Record Consultation"])
            UC6(["UC6 Record Home Visit"])
            UC7(["UC7 Record Vaccination"])
            UC8(["UC8 Distribute Medicine"])
            UC9(["UC9 Manage Follow-ups<br/>(Add / Update Status)"])
            UC10(["UC10 View Patient<br/>Profile / History"])
        end

        subgraph REPORT["Reporting"]
            UC11(["UC11 Generate Reports<br/>(Filter / Export CSV / Print)"])
        end

        subgraph ADMINONLY["Admin-Only Management"]
            UC12(["UC12 Manage BHW<br/>Worker Accounts"])
            UC13(["UC13 View Login<br/>History / Audit"])
        end
    end

    %% ===== Actor associations =====
    BHW --- UC1
    BHW --- UC2
    BHW --- UC3
    BHW --- UC4
    BHW --- UC5
    BHW --- UC6
    BHW --- UC7
    BHW --- UC8
    BHW --- UC9
    BHW --- UC10
    BHW --- UC11

    ADMIN --- UC1
    ADMIN --- UC2
    ADMIN --- UC3
    ADMIN --- UC5
    ADMIN --- UC11
    ADMIN --- UC12
    ADMIN --- UC13

    %% ===== Include relationships =====
    UC2 -.->|"«include»"| UC1
    UC3 -.->|"«include»"| UC1

---

## 3. Data Flow Diagram — Level 0 (Context Diagram)

```mermaid
flowchart TB
    BHW(["👤 BHW Worker"])
    ADMIN(["👤 Administrator"])

    P0(("0.0<br/>BHW Assist<br/>Management System"))

    DB[("D0: bhw_assist<br/>Database")]

    BHW -->|"Credentials; patient &<br/>health-service inputs;<br/>report queries"| P0
    P0 -->|"Dashboard analytics;<br/>record lists; generated<br/>reports / CSV"| BHW

    ADMIN -->|"Credentials; account<br/>create/update; audit<br/>queries"| P0
    P0 -->|"Account lists;<br/>login/audit trail"| ADMIN

    P0 <-->|"SQL SELECT / INSERT /<br/>UPDATE / DELETE"| DB
```

---

## 4. Data Flow Diagram — Level 1

```mermaid
flowchart TB
    %% ===== External Entities =====
    BHW(["👤 BHW Worker"])
    ADMIN(["👤 Administrator"])

    %% ===== Processes =====
    P1(["1.0 Authentication<br/>& Session Control"])
    P2(["2.0 Patient<br/>Management"])
    P3(["3.0 Health Services<br/>Recording"])
    P4(["4.0 Follow-up<br/>Management"])
    P5(["5.0 Reports &<br/>Analytics"])
    P6(["6.0 Account & Audit<br/>Management (Admin)"])

    %% ===== Data Stores =====
    D1[("D1 users")]
    D2[("D2 login_history")]
    D3[("D3 patients")]
    D4[("D4 consultations")]
    D5[("D5 home_visits")]
    D6[("D6 vaccinations")]
    D7[("D7 medicines")]
    D8[("D8 followups")]

    %% ===== Auth flows =====
    BHW -->|"login credentials"| P1
    ADMIN -->|"login credentials"| P1
    P1 -->|"session + role"| BHW
    P1 -->|"session + role"| ADMIN
    P1 <-->|"verify password_hash /<br/>read role"| D1
    P1 -->|"login_at, ip,<br/>logout_at"| D2

    %% ===== Patient management =====
    BHW -->|"patient details /<br/>CSV file / search"| P2
    P2 -->|"patient record /<br/>PAT-######"| BHW
    P2 <-->|"CRUD patients"| D3

    %% ===== Health services =====
    BHW -->|"consultation / visit /<br/>vaccine / medicine data"| P3
    P3 -->|"save confirmation"| BHW
    P2 -.->|"patient_id"| P3
    P3 -->|"insert"| D4
    P3 -->|"insert"| D5
    P3 -->|"insert"| D6
    P3 -->|"insert"| D7
    P3 -.->|"followup_date trigger"| P4

    %% ===== Follow-ups =====
    BHW -->|"follow-up add /<br/>status update"| P4
    P4 -->|"follow-up list<br/>(Pending/Overdue/Completed)"| BHW
    P2 -.->|"patient_id"| P4
    P4 <-->|"CRUD followups"| D8

    %% ===== Reports & analytics =====

---

## 5. Entity Relationship Diagram (Database: `bhw_assist`)

```mermaid
erDiagram
    users ||--o{ login_history : "generates"
    patients ||--o{ consultations : "has"
    patients ||--o{ home_visits : "has"
    patients ||--o{ vaccinations : "has"
    patients ||--o{ medicines : "receives"
    patients ||--o{ followups : "has"

    users {
        INT id PK
        VARCHAR username UK
        VARCHAR password
        VARCHAR role "Admin | BHW"
    }
    login_history {
        INT id PK
        INT user_id FK
        VARCHAR username
        DATETIME login_at
        DATETIME logout_at
        VARCHAR ip_address
        VARCHAR role
    }
    patients {
        INT id PK
        VARCHAR patient_no UK
        VARCHAR full_name
        VARCHAR first_name
        VARCHAR last_name
        INT age
        VARCHAR sex
        VARCHAR address
        VARCHAR house_no
        VARCHAR street
        VARCHAR purok
        VARCHAR contact
        TIMESTAMP created_at
    }
    consultations {
        INT id PK
        INT patient_id FK
        DATE consultation_date
        TEXT complaint
        TEXT assessment
        TEXT action_taken
        DATE followup_date
    }
    home_visits {
        INT id PK
        INT patient_id FK
        DATE visit_date
        TEXT reason
        TEXT findings
        TEXT action_taken
    }
    vaccinations {
        INT id PK
        INT patient_id FK
        VARCHAR vaccine
        VARCHAR dose
        DATE vaccination_date
        DATE next_date
    }
    medicines {
        INT id PK
        INT patient_id FK
        VARCHAR medicine_name
        INT quantity
        DATE distribution_date
        VARCHAR purpose
    }
    followups {
        INT id PK
        INT patient_id FK
        DATE followup_date
        TEXT reason
        VARCHAR status "Pending|Completed|Cancelled|Overdue*"
        TEXT notes
    }
```

> `*Overdue` is a derived status (shown in UI) computed as `status = 'Pending' AND followup_date < CURDATE()`; it is not physically stored.

---

## 6. Key Implementation Notes (for reference)

- **Auth guard:** Every page `require_once config.php` + `includes/auth.php`, then calls `require_login()` (all) or `require_admin()` (BHW Workers, Login History).
- **CSRF:** All POST forms carry `csrf_token()`; writes call `verify_csrf()` (returns HTTP 419 on mismatch).
- **Password handling:** `password_verify()` with legacy plaintext fallback that transparently re-hashes to bcrypt on successful login.
- **Patient numbering:** New patients first get a `TEMP-*` id, then are updated to `PAT-%06d` inside a DB transaction (add & CSV import both use this pattern).
- **Patient validation:** Requires first+last name, valid sex (Male/Female), and contact exactly 11 digits starting with `09`. Duplicate detection is by `full_name + age + address`.
- **Session audit:** On login a `login_history` row is inserted (id stored in session); on logout `logout_at` is stamped via `logout.php`.
- **Reports:** Dynamic per-type SQL config, optional date-range and name filters, CSV export via `?export=csv`, and print-friendly view with sign-off block.

    BHW -->|"report type, date<br/>range, name filter"| P5
    P5 -->|"rendered report /<br/>CSV download"| BHW
    ADMIN -->|"report type, filters"| P5
    P5 -->|"report / CSV"| ADMIN
    P5 -.->|"read"| D3
    P5 -.->|"read"| D4
    P5 -.->|"read"| D5
    P5 -.->|"read"| D6
    P5 -.->|"read"| D7
    P5 -.->|"read"| D8

    %% ===== Admin account & audit =====
    ADMIN -->|"account username,<br/>password, role"| P6
    P6 -->|"account list"| ADMIN
    P6 <-->|"CRUD users"| D1
    ADMIN -->|"audit search /<br/>date filter"| P6
    P6 -->|"login history"| ADMIN
    P6 -.->|"read"| D2
```

    UC5 -.->|"«include»"| UC1
    UC6 -.->|"«include»"| UC1
    UC7 -.->|"«include»"| UC1
    UC8 -.->|"«include»"| UC1
    UC9 -.->|"«include»"| UC1
    UC10 -.->|"«include»"| UC1
    UC11 -.->|"«include»"| UC1
    UC12 -.->|"«include» require_admin"| UC1
    UC13 -.->|"«include» require_admin"| UC1

    %% ===== Extend / usage relationships =====
    UC10 -.->|"«extend» views records of"| UC5
    UC10 -.->|"«extend» views records of"| UC6
    UC5 -.->|"«extend» auto-creates<br/>follow-up reminder"| UC9
    UC4 -.->|"«extend» uses template"| UC3
    UC3 -.->|"«navigate»"| UC10
```
