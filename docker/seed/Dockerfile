FROM python:3.12-slim

# Build context = project root, filtered by .dockerignore (only these 2 files are sent)
COPY seed/requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt

COPY docker/seed/run_seed.py /opt/run_seed.py

# seed/ is mounted here at runtime (seed_data.json in, inject_report.json out)
WORKDIR /app
CMD ["python", "/opt/run_seed.py"]
