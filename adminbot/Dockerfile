FROM node:20-slim

# chromium runtime deps for puppeteer
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates fonts-liberation libasound2 libatk-bridge2.0-0 \
    libatk1.0-0 libcups2 libdbus-1-3 libdrm2 libgbm1 libgtk-3-0 \
    libnspr4 libnss3 libx11-6 libxcomposite1 libxdamage1 libxext6 \
    libxfixes3 libxrandr2 libxkbcommon0 wget \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /bot
COPY package.json .
RUN npm install --omit=dev
COPY bot.js .

CMD ["node", "bot.js"]
