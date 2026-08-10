#!/usr/bin/env bash
# Bootstrap a fresh Ubuntu 22.04+ VPS for Cash Flow Summary (Step 112).
# Run as root on the server (or via sudo bash deploy/vps/bootstrap-server.sh).
#
# Step 113 adds SSH hardening, UFW, and TLS.
# Step 114 deploys the application stack.

set -euo pipefail

DEPLOY_USER="${DEPLOY_USER:-deploy}"
APP_DIR="${APP_DIR:-/var/www/cashflow-summary}"
INSTALL_DOCKER="${INSTALL_DOCKER:-1}"

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    echo "Run as root: sudo bash $0" >&2
    exit 1
fi

. /etc/os-release
if [[ "${ID:-}" != "ubuntu" ]]; then
    echo "This script targets Ubuntu LTS. Detected: ${ID:-unknown}" >&2
    exit 1
fi

VERSION_ID_NUM="${VERSION_ID%%.*}"
if [[ "${VERSION_ID_NUM:-0}" -lt 22 ]]; then
    echo "Ubuntu 22.04+ required. Detected: ${VERSION_ID:-unknown}" >&2
    exit 1
fi

echo "==> Updating packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get upgrade -y
apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    git \
    gnupg \
    lsb-release \
    software-properties-common \
    unattended-upgrades

echo "==> Enabling unattended security updates"
dpkg-reconfigure -plow unattended-upgrades || true

if [[ "$INSTALL_DOCKER" == "1" ]]; then
    if ! command -v docker >/dev/null 2>&1; then
        echo "==> Installing Docker Engine + Compose plugin"
        install -m 0755 -d /etc/apt/keyrings
        curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
        chmod a+r /etc/apt/keyrings/docker.asc
        echo \
          "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
          $(. /etc/os-release && echo "${VERSION_CODENAME}") stable" \
          > /etc/apt/sources.list.d/docker.list
        apt-get update
        apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    else
        echo "==> Docker already installed"
    fi
    systemctl enable docker
    systemctl start docker
fi

if ! id "$DEPLOY_USER" &>/dev/null; then
    echo "==> Creating deploy user: $DEPLOY_USER"
    useradd --create-home --shell /bin/bash "$DEPLOY_USER"
else
    echo "==> Deploy user exists: $DEPLOY_USER"
fi

usermod -aG docker "$DEPLOY_USER"

echo "==> Preparing application directory: $APP_DIR"
mkdir -p "$APP_DIR"
chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "$APP_DIR"

echo ""
echo "Bootstrap complete."
echo ""
echo "Next (as $DEPLOY_USER):"
echo "  1. Add your SSH public key to /home/$DEPLOY_USER/.ssh/authorized_keys"
echo "  2. Clone the repo into $APP_DIR"
echo "  3. cp deploy/env/production.env.example deploy/env/production.env && edit secrets"
echo "  4. bash deploy/vps/verify-provision.sh"
echo ""
echo "Then Step 113: SSH hardening, UFW (22/80/443), TLS"
