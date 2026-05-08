---
title: Product Requirements Document (PRD)
date: 2026-04-23
author: Bang
---

# Product Requirements Document

## 1. Purpose

An IELTS Learning Management System (LMS) enabling students to enroll in courses, complete lessons and assignments, and track their progress — while instructors create and manage course content and grade submissions. Admins manage users and platform configuration.

## 2. Problem Statement

IELTS candidates lack a structured, self-paced platform to prepare for the exam. Instructors lack tooling to deliver content and track student progress at scale. This platform bridges both gaps with role-appropriate experiences for students, instructors, and administrators.

## 3. Target Users

- **Students:** IELTS candidates seeking structured exam preparation
- **Instructors:** IELTS educators creating and managing course content, grading submissions
- **Admins:** Platform operators managing users and system configuration

## 4. Goals & Success Metrics

- Goal 1: Students can self-enroll in courses and complete all lesson + assignment content
- Goal 2: Instructors can create courses, add lessons/assignments, and grade submissions without admin intervention
- Goal 3: Platform enforces role-appropriate access (RBAC) and contextual rules (ABAC) at every endpoint
- Metric: API p95 response time < 300ms for all list endpoints under normal load

## 5. Key User Journeys

- Student registers → verifies email → enrolls in course → completes lessons → submits assignment → receives grade
- Instructor logs in → creates course → adds lessons and assignments → reviews and grades submissions
- Admin manages user roles and monitors platform activity

## 6. Functional Requirements (FR)

FR1: User registration — The system shall allow a user to register with email and password and receive a verification email.

FR2: User login — The system shall allow a registered user to authenticate and obtain a JWT bearer token (`{ token, token_type: "bearer", expires_in: 3600 }`) for API requests.

FR3: Password reset — The system shall allow users to request a password reset email and reset their password securely.

FR4: Profile management — The system shall allow authenticated users to view and update their profile data (name, avatar, preferences).

FR5: Course management — Instructors shall be able to create, update, and delete courses; students shall be able to view and enroll in courses.

FR6: Lesson management — Instructors shall be able to create and manage lessons nested within courses; enrolled students shall be able to view lesson content.

FR7: Assignment management — Instructors shall be able to create assignments with deadlines; students shall be able to submit assignments before the deadline.

FR8: Grading — Instructors shall be able to grade student submissions; students shall be able to view their own grades.

FR9: Enrollment — Students shall be able to enroll in open courses; enrollment gates access to lessons and assignments.

FR10: Pagination and filtering — The system shall provide paginated APIs with server-side filtering for all list endpoints.

## 7. Non-Functional Requirements (NFR)

NFR1: Performance — API endpoints shall respond within 300ms p95 under normal load for common GET requests.

NFR2: Availability — The system shall maintain 99.9% uptime for user-facing APIs monthly.

NFR3: Security — All sensitive data at rest and in transit must be encrypted; passwords must be hashed with Argon2 or bcrypt.

NFR4: Scalability — The system shall scale horizontally for stateless services (backend/frontend) and support read-replicas for the DB.

NFR5: Observability — Application logs must be structured JSON and exported to the central logging system; critical errors sent to Sentry.

## 8. Requirement Traceability

- FR1, FR2, FR3, FR4 → Epic 2: User Identity & Account Management
- FR5, FR6, FR7, FR8, FR9, FR10 → Epic 4: IELTS LMS Domain
