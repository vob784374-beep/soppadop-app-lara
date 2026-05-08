# Kiến trúc hệ thống — Laravel backend + Next.js frontend (Production)

Tài liệu này tóm tắt các quyết định kỹ thuật chính, luồng dữ liệu, và các thành phần vận hành cho kiến trúc production.

## 1. Tóm tắt kiến trúc
- Frontend: Next.js (SSR/ISR) + Tailwind, chạy trên container (Node) hoặc static export + CDN.
- Backend: Laravel (PHP 8.1+, Laravel 10+) chạy trên PHP-FPM, phục vụ qua Nginx.
- DB: Managed MySQL (RDS/Aurora). Read replicas cho scale đọc.
- Cache/Queue: Redis (ElastiCache / managed Redis).
- File storage: S3 (AWS S3 / compatible) + CDN (CloudFront / Cloudflare).
- Container orchestration: Kubernetes (EKS/GKE/AKS) hoặc managed containers (ECS/Fargate).

## 2. Luồng yêu cầu (simplified)

```mermaid
flowchart LR
  A[User Browser] -->|HTTPS| CDN
  CDN --> F[Next.js Frontend (Edge/SSR)]
  F -->|API| LB[Ingress / Load Balancer]
  LB --> Nginx
  Nginx --> PHP[PHP-FPM (Laravel)]
  PHP --> DB[(MySQL Primary)]
  PHP --> Redis[(Redis Cache / Queue)]
  PHP --> S3[(Object Storage)]
  Redis --> QueueWorkers[Laravel Queue Workers]
  Logs --> ELK[Log Aggregation]
  Metrics --> Prometheus
  Alerts --> Alertmanager
```

## 3. Quyết định thiết kế và lý do
- Containerization: đảm bảo parity dev/prod, dễ CI/CD.
- PHP-FPM + Nginx: tiêu chuẩn cho Laravel, cho phép tối ưu bằng opcode cache và static file offload.
- Managed DB & Redis: giảm operational burden, tăng reliability.
- CI/CD: Build images, run migrations in job with readiness probes; deploy manifests via `kubectl` hoặc helm.
- Zero-downtime deploys: rolling updates, use feature flags và online-safe DB migrations.

## 4. Observability
- Logs: structured JSON logs from app → central (ElasticSearch / Datadog / CloudWatch).
- Metrics: Prometheus scraping (app metrics + kube metrics) + Grafana dashboards.
- Tracing: OpenTelemetry / Jaeger for request traces (optional for deep debugging).
- Error tracking: Sentry integration in both backend & frontend.

## 5. Security
- TLS termination at CDN/ALB.
- WAF in front of app for common attack protection.
- Secrets via secret manager (Vault / AWS Secrets Manager).
- Least-privilege IAM roles for services.

## 6. Deployment snippets & commands
- Local dev (docker-compose):

```bash
docker-compose up --build
```

- Deploy (CI) will build images and apply manifests:

```bash
# Example
kubectl apply -f infra/k8s/backend-deployment.yaml
kubectl apply -f infra/k8s/frontend-deployment.yaml
```

## 7. Next steps (recommended)
1. Harden CI: add image scanning, dependency checks, automated migration checks.
2. Add Helm charts for all services and manage values per environment.
3. Implement Prometheus + Grafana dashboards and SLOs.
