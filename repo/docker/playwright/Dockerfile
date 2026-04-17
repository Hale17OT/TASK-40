FROM mcr.microsoft.com/playwright:v1.59.1-jammy

WORKDIR /tests

# Install E2E dependencies at IMAGE BUILD TIME — no runtime install needed.
COPY src/tests/E2E/package.json src/tests/E2E/package-lock.json* ./
RUN npm install --silent

# Browsers are pre-installed in the base image; nothing else to fetch.

# Copy spec files (bind-mounted at runtime too, but this makes the image
# self-contained for CI / air-gapped runs).
COPY src/tests/E2E/ /tests/

CMD ["npx", "playwright", "test", "--reporter=list"]
