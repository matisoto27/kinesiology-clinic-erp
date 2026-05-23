# Kinesiology Clinic ERP

A fullstack ERP/web application developed to digitize and centralize the daily operations of a kinesiology clinic.

The project was created to replace fragmented Excel-based workflows that were causing operational friction, accidental data loss, scheduling inconsistencies, and inefficient administrative processes.

The system centralizes patient management, appointment scheduling, healthcare activity administration, payments, attendance tracking, and operational workflows into a single platform designed for real-world clinic usage.

This project was fully designed and developed by a single developer, covering backend architecture, database modeling, business logic, administrative workflows, and frontend implementation.

---

# Features

## Patient Management

* Patient registration and profile management
* Contact and emergency information handling
* Medical and administrative data organization
* Searchable patient records
* Historical activity tracking

## Appointment & Scheduling System

* Appointment creation and management
* Professional schedule administration
* Attendance tracking workflows
* Flexible appointment rescheduling logic
* Monthly appointment generation automation
* Calendar-oriented operational management
* Validation mechanisms to reduce scheduling inconsistencies

## Healthcare Activity Management

* Management of healthcare activities and treatment workflows
* Assignment of patients to activities and professionals
* Subscription and recurring scheduling support
* Configurable operational rules for activities and appointments

## Administrative & Financial Features

* Payment registration and tracking
* Cash flow and operational movement management
* Administrative dashboards and operational tools
* Healthcare provider / insurance administration
* Expense and income tracking

## Authentication & Authorization

* User authentication system
* Role-based access control
* Protected administrative routes
* Permission-based workflow separation

## User Experience & Productivity

* Reactive and dynamic administrative interfaces
* Fast server-driven interactions using Livewire
* Search and filtering workflows for operational efficiency
* Reusable form and validation systems
* Optimized workflows for daily clinic operations

---

# Tech Stack

## Backend

* PHP
* Laravel
* Eloquent ORM
* Laravel Middleware
* MVC Architecture

## Frontend

* Laravel Blade
* Laravel Livewire
* JavaScript
* HTML/CSS
* Tailwind CSS
* Alpine.js

## Database

* Relational Database Modeling
* MySQL / MariaDB-compatible architecture

## Development Tools

* Composer
* Artisan CLI
* Git

---

# Architecture & Design

The project follows a server-driven fullstack architecture using Laravel and Livewire, prioritizing maintainability, clear separation of responsibilities, and operational reliability.

## Project Organization

The application is structured around:

* Controllers for request handling
* Eloquent Models for domain representation
* Blade and Livewire components for UI rendering
* Middleware for authentication and authorization
* Validation layers for data integrity
* Route grouping and modular organization

## Separation of Responsibilities

The system separates:

* Business logic
* Presentation logic
* Persistence concerns
* Authentication and access control
* Administrative workflows

This approach improves maintainability and allows domain logic to evolve without tightly coupling it to the interface layer.

## Reactive UI with Livewire

The frontend uses Laravel Livewire to provide reactive interfaces without requiring a separate SPA architecture.

This enables:

* Dynamic forms
* Real-time-like interactions
* Efficient administrative workflows
* Reduced frontend complexity
* Faster iteration for business-oriented features

## Authentication & Access Control

The application includes:

* Authentication workflows
* Session-based access management
* Middleware-protected routes
* Role-based administrative separation

This allows different operational areas of the system to remain isolated and secure.

## Database Design

The relational database structure models real operational workflows including:

* Patients
* Professionals
* Appointments
* Activities
* Payments
* Attendance
* Financial movements
* Administrative entities

The data model was designed to maintain consistency across interconnected operational processes.

---

# Technical Challenges Solved

## Replacing Fragile Spreadsheet-Based Workflows

One of the main challenges was centralizing operational data that was previously managed across multiple spreadsheets.

The system was designed to reduce:

* Accidental data modification
* Loss of operational information
* Scheduling inconsistencies
* Manual duplication of work
* Fragmented administrative processes

## Appointment Automation

The project includes automation mechanisms for recurring appointment generation and operational scheduling workflows.

This reduced repetitive administrative tasks while maintaining flexibility for manual adjustments and validation.

## Data Consistency & Validation

The application implements multiple validation layers to ensure operational consistency across scheduling, attendance, payments, and administrative workflows.

Examples include:

* Validation of scheduling conflicts
* State-aware workflow handling
* Form validation and sanitization
* Relationship integrity between operational entities

## Managing Complex Business Relationships

The platform models interconnected workflows between:

* Patients
* Professionals
* Activities
* Financial operations
* Scheduling systems
* Administrative processes

Maintaining consistency across these relationships required careful relational modeling and workflow organization.

## Balancing Complexity & Usability

A key challenge was building administrative tools capable of handling real operational complexity while remaining practical for non-technical clinic staff.

This influenced:

* UI design decisions
* Search workflows
* Form organization
* Navigation structure
* Operational dashboard design

## Server-Driven Reactive Interfaces

Using Livewire introduced the challenge of creating responsive interfaces while keeping frontend complexity manageable.

The project balances:

* Dynamic interactions
* Simpler deployment architecture
* Faster backend-driven iteration
* Reduced frontend state-management overhead

---

# Screenshots

## Patient Administration

![Patients Screenshot](./screenshots/patients.png)

## Appointment Management

![Appointments Screenshot](./screenshots/appointments.png)

## Financial Management

![Finance Screenshot](./screenshots/cash_flow.png)

---

# Getting Started

## Requirements

* PHP 8+
* Composer
* MySQL
* Node.js & NPM (if frontend assets need compilation)

## Installation

```bash
# Clone repository
git clone https://github.com/matisoto27/kinesiology-clinic-erp.git

# Enter project directory
cd kinesiology-clinic-erp

# Install PHP dependencies
composer install

# Copy environment configuration
cp .env.example .env

# Generate application key
php artisan key:generate
```

## Database Setup

Configure database credentials inside `.env`, then run:

```bash
php artisan migrate:fresh --seed
```

## Running the Application

```bash
php artisan serve
```

If frontend assets are required:

```bash
npm install
npm run dev
```

---

# What I Learned

This project provided hands-on experience designing and maintaining a real-world business management system used to solve operational problems in a healthcare environment.

Key areas of growth included:

* Backend architecture with Laravel
* Relational database design
* Operational workflow modeling
* Building maintainable administrative systems
* Reactive fullstack development with Livewire
* Validation and data consistency strategies
* Translating real business requirements into scalable software workflows
* Balancing technical complexity with usability for non-technical users

The project also reinforced the importance of designing software around reliability, maintainability, and operational clarity rather than only feature delivery.

---

# Notes

* This repository is intended to showcase technical architecture, engineering decisions, and practical problem-solving in a real-world business environment.
* Some business-specific rules and operational details have been intentionally generalized or omitted.
* The system was developed as a real solution for daily clinic operations rather than as a tutorial or academic project.
* The project was fully developed by a single developer, including architecture, backend implementation, database design, and frontend integration.