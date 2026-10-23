#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# BaoTang360 – kịch bản cài đặt một lệnh trên máy ảo Oracle Cloud "Always Free"
# Môi trường đích: Ubuntu 22.04 / 24.04, kiến trúc ARM64 (Ampere A1) hoặc x86_64.
#
# Kịch bản thực hiện:
#   1. Kiểm tra môi trường, cài Docker Engine + Docker Compose plugin.
#   2. Mở cổng 80/443 trong iptables của máy ảo và lưu cấu hình.
#   3. Tải mã nguồn, sinh tệp .env.prod với các bí mật ngẫu nhiên.
#   4. Dựng image và khởi chạy toàn hệ thống.
#
# LƯU Ý: Ngoài tường lửa trong máy ảo, PHẢI mở cổng 80/443 ở lớp mạng của
# Oracle Cloud (Virtual Cloud Network → Security Lists → Ingress Rules).
# Việc này thực hiện trên giao diện web, kịch bản không làm thay được.
#
# Cách dùng:
#   chmod +x oracle-setup.sh
#   ./oracle-setup.sh                 # tự suy ra tên miền <IP>.sslip.io
#   ./oracle-setup.sh baotang360.id.vn ten@phenikaa-uni.edu.vn
# ---------------------------------------------------------------------------
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/24100161-Hung/Nhom8-Baotang360.git}"
TARGET_DIR="${TARGET_DIR:-$HOME/baotang360}"
APP_DOMAIN_ARG="${1:-}"
ACME_EMAIL_ARG="${2:-}"

log()  { printf '\033[1;34m[baotang360]\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31m[lỗi]\033[0m %s\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] && fail "Không chạy bằng root. Dùng tài khoản ubuntu với sudo."
command -v sudo >/dev/null || fail "Thiếu sudo."

# --- 1. Docker ------------------------------------------------------------
if ! command -v docker >/dev/null; then
  log "Cài đặt Docker Engine ..."
  sudo apt-get update -qq
  sudo apt-get install -y -qq ca-certificates curl gnupg git
  sudo install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  sudo chmod a+r /etc/apt/keyrings/docker.gpg
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
    | sudo tee /etc/apt/sources.list.d/docker.list >/dev/null
  sudo apt-get update -qq
  sudo apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  sudo usermod -aG docker "$USER"
  log "Đã cài Docker. Tài khoản $USER được thêm vào nhóm docker."
else
  log "Docker đã có: $(docker --version)"
fi

# --- 2. Tường lửa trong máy ảo -------------------------------------------
log "Mở cổng 80/443 trong iptables ..."
sudo apt-get install -y -qq iptables-persistent >/dev/null 2>&1 || true
for PORT in 80 443; do
  if ! sudo iptables -C INPUT -p tcp --dport "$PORT" -j ACCEPT 2>/dev/null; then
    # Chèn lên đầu chuỗi INPUT để đứng TRƯỚC luật REJECT mặc định của ảnh Ubuntu
    # do Oracle cung cấp (luật REJECT nằm ở cuối chuỗi).
    sudo iptables -I INPUT 1 -p tcp --dport "$PORT" -j ACCEPT
  fi
done
sudo netfilter-persistent save >/dev/null 2>&1 || sudo sh -c 'iptables-save > /etc/iptables/rules.v4'

# --- 3. Mã nguồn ----------------------------------------------------------
if [ -d "$TARGET_DIR/.git" ]; then
  log "Cập nhật mã nguồn trong $TARGET_DIR ..."
  git -C "$TARGET_DIR" pull --ff-only
else
  log "Tải mã nguồn về $TARGET_DIR ..."
  git clone --depth 1 "$REPO_URL" "$TARGET_DIR"
fi
cd "$TARGET_DIR"

[ -f compose.prod.yaml ] || fail "Không thấy compose.prod.yaml ở gốc kho. Hãy chép tệp này vào kho trước."

# --- 4. Tên miền ----------------------------------------------------------
PUBLIC_IP="$(curl -fsS --max-time 10 https://api.ipify.org || true)"
[ -n "$PUBLIC_IP" ] || fail "Không xác định được địa chỉ IP công cộng."
if [ -n "$APP_DOMAIN_ARG" ]; then
  APP_DOMAIN="$APP_DOMAIN_ARG"
else
  APP_DOMAIN="${PUBLIC_IP//./-}.sslip.io"
fi
log "Tên miền sử dụng: $APP_DOMAIN  (IP công cộng: $PUBLIC_IP)"

# --- 5. Tệp .env.prod -----------------------------------------------------
if [ ! -f .env.prod ]; then
  log "Sinh .env.prod với các bí mật ngẫu nhiên ..."
  rnd() { openssl rand -hex "${1:-24}"; }
  cat > .env.prod <<EOF
# Sinh tự động bởi oracle-setup.sh – KHÔNG đưa tệp này lên kho Git
APP_DOMAIN=$APP_DOMAIN
ACME_EMAIL=$ACME_EMAIL_ARG
APP_KEY=base64:$(openssl rand -base64 32)
DB_DATABASE=baotang360
DB_USERNAME=baotang360
DB_PASSWORD=$(rnd 20)
DB_ROOT_PASSWORD=$(rnd 20)
ANALYTICS_TOKEN=$(rnd 24)
PAYMENT_SANDBOX_SECRET=$(rnd 32)
SEED_DEMO_PASSWORD=BaoTang@$(date +%Y)
ORDER_HOLD_MINUTES=15
EOF
  chmod 600 .env.prod
else
  log ".env.prod đã tồn tại – giữ nguyên. Cập nhật APP_DOMAIN nếu cần."
  sed -i "s|^APP_DOMAIN=.*|APP_DOMAIN=$APP_DOMAIN|" .env.prod
fi

# --- 6. Dựng và khởi chạy -------------------------------------------------
DC="docker compose"
docker info >/dev/null 2>&1 || DC="sudo docker compose"
log "Dựng image (lần đầu khoảng 15–25 phút trên máy ảo ARM) ..."
$DC -f compose.prod.yaml --env-file .env.prod up -d --build

log "Chờ dịch vụ sẵn sàng ..."
for i in $(seq 1 60); do
  if curl -fsS --max-time 5 "http://127.0.0.1/healthz" >/dev/null 2>&1 \
  || curl -fsSk --max-time 5 "https://127.0.0.1/healthz" >/dev/null 2>&1; then
    break
  fi
  sleep 10
done

cat <<EOF

======================================================================
 Hoàn tất. Địa chỉ truy cập:  https://$APP_DOMAIN
 Tài khoản mẫu: xem storage/logs hoặc tài liệu triển khai.
 Mật khẩu tài khoản mẫu nằm ở biến SEED_DEMO_PASSWORD trong .env.prod

 Nếu trang không mở được, kiểm tra theo thứ tự:
   1. Ingress Rule 80/443 trong Oracle Cloud → Networking → VCN →
      Security Lists (lỗi hay gặp nhất).
   2. $DC -f compose.prod.yaml logs -f caddy   (chứng thư TLS)
   3. $DC -f compose.prod.yaml logs -f web     (migrate, seed)
======================================================================
EOF
