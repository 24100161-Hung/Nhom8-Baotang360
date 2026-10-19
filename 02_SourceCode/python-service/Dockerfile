FROM python:3.12-slim
WORKDIR /srv
ENV PYTHONDONTWRITEBYTECODE=1 PYTHONUNBUFFERED=1 STORAGE_ROOT=/srv/storage
RUN apt-get update && apt-get install -y --no-install-recommends curl && rm -rf /var/lib/apt/lists/*
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip && pip install --no-cache-dir -r requirements.txt
COPY app ./app
COPY kiem_dinh_du_lieu.py .
# /srv/storage: điểm mount volume storage/app của Laravel (tiles ghi vào public/tiles)
RUN useradd --system --uid 10001 --no-create-home analytics && mkdir -p /srv/storage/public/tiles \
    && chown -R analytics:analytics /srv
USER analytics
EXPOSE 8001
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s CMD curl -fsS http://localhost:8001/healthz || exit 1
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001"]
