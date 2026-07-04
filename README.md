# PHP Test App (Docker + GKE)

Aplicación web PHP mínima que responde `solicitud recibida` y registra cada petición HTTP en un log JSON.

## Desarrollo local

### Requisitos

- Docker
- Docker Compose

### Uso

```bash
docker compose up --build
```

La app queda disponible en: http://localhost:8080

### Ejemplos

```bash
# GET simple
curl http://localhost:8080/

# GET con query string
curl "http://localhost:8080/?nombre=test&valor=123"

# POST con JSON
curl -X POST http://localhost:8080/ \
  -H "Content-Type: application/json" \
  -d '{"lolo_concepto":"prueba","lolo_detalle":"detalle de prueba"}'
```

En local, los registros se guardan en `logs/php-error.log`.

---

## Despliegue en GKE con Cloud Build

Al hacer **push** al repositorio, Cloud Build:

1. Construye la imagen Docker
2. La sube a **Artifact Registry**
3. Despliega la carga de trabajo **`aplicacion`** en el cluster **`autopilot-cluster-1`**

### Archivos de despliegue

| Archivo | Propósito |
|---------|-----------|
| `cloudbuild.yaml` | Pipeline de build, push y deploy |
| `k8s/deployment.yaml` | Deployment `aplicacion` |
| `k8s/service.yaml` | Service `aplicacion` (ClusterIP :80) |

### Variables en `cloudbuild.yaml`

Ajusta estas sustituciones según tu proyecto GCP:

| Variable | Valor por defecto | Descripción |
|----------|-------------------|-------------|
| `_GKE_CLUSTER` | `autopilot-cluster-1` | Nombre del cluster |
| `_GKE_LOCATION` | `us-central1` | Región o zona del cluster |
| `_AR_REGION` | `us-central1` | Región de Artifact Registry |
| `_AR_REPOSITORY` | `aplicacion` | Repositorio de imágenes |
| `_IMAGE_NAME` | `aplicacion` | Nombre de la imagen |
| `_K8S_NAMESPACE` | `default` | Namespace de Kubernetes |

### Configuración inicial en GCP (una sola vez)

Reemplaza `TU_PROYECTO`, `TU_REGION` y `TU_NUMERO` según tu entorno:

```bash
# 1. Proyecto activo
gcloud config set project TU_PROYECTO

# 2. Habilitar APIs necesarias
gcloud services enable \
  cloudbuild.googleapis.com \
  container.googleapis.com \
  artifactregistry.googleapis.com

# 3. Crear repositorio en Artifact Registry
gcloud artifacts repositories create aplicacion \
  --repository-format=docker \
  --location=TU_REGION \
  --description="Imagenes de la app PHP"

# 4. Permisos de Cloud Build sobre GKE y Artifact Registry
PROJECT_NUMBER=$(gcloud projects describe TU_PROYECTO --format='value(projectNumber)')

gcloud projects add-iam-policy-binding TU_PROYECTO \
  --member="serviceAccount:${PROJECT_NUMBER}@cloudbuild.gserviceaccount.com" \
  --role="roles/container.developer"

gcloud projects add-iam-policy-binding TU_PROYECTO \
  --member="serviceAccount:${PROJECT_NUMBER}@cloudbuild.gserviceaccount.com" \
  --role="roles/artifactregistry.writer"
```

### Crear el trigger de Cloud Build (push automático)

**Opción A — Repositorio en Cloud Source Repositories o GitHub conectado:**

```bash
gcloud builds triggers create github \
  --name="deploy-aplicacion" \
  --repo-name=TU_REPO \
  --repo-owner=TU_USUARIO_O_ORG \
  --branch-pattern="^main$" \
  --build-config=cloudbuild.yaml \
  --substitutions=_GKE_LOCATION=TU_REGION,_AR_REGION=TU_REGION
```

**Opción B — Desde la consola GCP:**

1. Ve a **Cloud Build → Triggers → Crear trigger**
2. Conecta tu repositorio (GitHub, GitLab, etc.)
3. Evento: **Push** a la rama `main`
4. Archivo de configuración: `cloudbuild.yaml`
5. En sustituciones, define `_GKE_LOCATION` y `_AR_REGION` con la región real de tu cluster

### Despliegue manual (sin esperar el trigger)

```bash
gcloud builds submit --config=cloudbuild.yaml \
  --substitutions=_GKE_LOCATION=TU_REGION,_AR_REGION=TU_REGION
```

### Verificar en el cluster

```bash
gcloud container clusters get-credentials autopilot-cluster-1 --region=TU_REGION

kubectl get deployment aplicacion
kubectl get pods -l app=aplicacion
kubectl logs -l app=aplicacion -f
```

### Logs en GKE

En el cluster, los logs JSON van a **stderr** y aparecen en **Cloud Logging** al consultar los pods de `aplicacion`. No se usan volúmenes locales.

### Exponer la app

El Service es `ClusterIP`. Si ya tienes un **Ingress** o **Gateway** en el cluster, apúntalo al service `aplicacion` puerto `80`.

Para pruebas rápidas con IP pública:

```bash
kubectl patch service aplicacion -p '{"spec":{"type":"LoadBalancer"}}'
kubectl get service aplicacion
```

---

## Formato del log

```json
{
  "concepto": "solicitud_http",
  "detalle": "Petición recibida en index.php",
  "ip": "10.0.0.1",
  "ubicacion": "/?nombre=test",
  "tipo_log_id": 1,
  "transaccion_id": "req_...",
  "fecha": "2026-07-04 01:02:03",
  "request": {
    "metodo": "GET",
    "uri": "/?nombre=test",
    "query": {"nombre": "test"},
    "post": {},
    "body": null,
    "headers": {},
    "user_agent": "curl/8.x"
  }
}
```
