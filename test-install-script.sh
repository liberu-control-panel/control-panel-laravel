#!/bin/bash

################################################################################
# Test Script for install.sh
# This script validates the installation script without requiring root access
################################################################################

set -euo pipefail

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEST_RESULTS=0

echo "================================="
echo "Testing install.sh"
echo "================================="
echo ""

# Test 1: Script exists and is executable
echo -n "Test 1: Script exists and is executable... "
if [[ -f "$SCRIPT_DIR/install.sh" && -x "$SCRIPT_DIR/install.sh" ]]; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 2: Bash syntax is valid
echo -n "Test 2: Bash syntax is valid... "
if bash -n "$SCRIPT_DIR/install.sh" 2>/dev/null; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 3: Required functions exist
echo -n "Test 3: Required functions exist... "
REQUIRED_FUNCTIONS=(
    "display_banner"
    "check_root"
    "detect_os"
    "display_menu"
    "install_kubernetes"
    "install_docker"
    "install_standalone"
)

ALL_FUNCTIONS_EXIST=true
for func in "${REQUIRED_FUNCTIONS[@]}"; do
    if ! grep -q "^${func}()" "$SCRIPT_DIR/install.sh"; then
        echo -e "${RED}FAIL${NC} - Missing function: $func"
        ALL_FUNCTIONS_EXIST=false
        TEST_RESULTS=$((TEST_RESULTS + 1))
        break
    fi
done

if $ALL_FUNCTIONS_EXIST; then
    echo -e "${GREEN}PASS${NC}"
fi

# Test 4: OS detection logic exists
echo -n "Test 4: OS detection logic exists... "
if grep -q "ubuntu\|almalinux\|rhel\|rocky" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 5: Installation method options exist
echo -n "Test 5: Installation method options exist... "
if grep -q "Kubernetes\|Docker Compose\|Standalone" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 6: Supporting installation scripts exist
echo -n "Test 6: Supporting scripts exist... "
if [[ -f "$SCRIPT_DIR/install-k8s.sh" && -f "$SCRIPT_DIR/install-control-panel.sh" ]]; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 7: Documentation exists
echo -n "Test 7: Documentation exists... "
if [[ -f "$SCRIPT_DIR/docs/INSTALLATION_GUIDE.md" && -f "$SCRIPT_DIR/INSTALL_SCRIPT_README.md" ]]; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 8: Docker compose file exists
echo -n "Test 8: Docker compose configuration exists... "
if [[ -f "$SCRIPT_DIR/docker-compose.yml" ]]; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 9: README updated with installation info
echo -n "Test 9: README contains installation info... "
if grep -q "install.sh\|Unified Installation" "$SCRIPT_DIR/README.md"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 10: Script has proper shebang and error handling
echo -n "Test 10: Script has proper shebang and error handling... "
FIRST_LINE=$(head -1 "$SCRIPT_DIR/install.sh")
if [[ "$FIRST_LINE" == "#!/bin/bash" ]] && grep -q "set -euo pipefail" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 11: setup_sudo_access function exists for standalone sudo setup
echo -n "Test 11: setup_sudo_access function exists... "
if grep -q "^setup_sudo_access()" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 12: setup_sudo_access is called during standalone app setup
echo -n "Test 12: setup_sudo_access is called from setup_standalone_app... "
if awk '/^setup_standalone_app\(\)/,/^\}/' "$SCRIPT_DIR/install.sh" | grep -q "setup_sudo_access"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 13: sudoers file path is defined in setup_sudo_access
echo -n "Test 13: sudoers.d path defined in setup_sudo_access... "
if grep -q "sudoers.d/control-panel" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 14: Dedicated cp-panel service account creation function exists
echo -n "Test 14: create_control_panel_service_user function exists... "
if grep -q "^create_control_panel_service_user()" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 15: Dedicated PHP-FPM pool creation function exists
echo -n "Test 15: create_control_panel_php_fpm_pool function exists... "
if grep -q "^create_control_panel_php_fpm_pool()" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 16: sudo is scoped to cp-panel, NOT www-data or nginx
echo -n "Test 16: sudo is scoped to cp-panel service account only... "
# setup_sudo_access must reference cp-panel and must NOT grant sudo to www-data/nginx
SUDO_SECTION=$(awk '/^setup_sudo_access\(\)/,/^\}/' "$SCRIPT_DIR/install.sh")
if echo "$SUDO_SECTION" | grep -q 'CP_USER="cp-panel"' && \
   ! echo "$SUDO_SECTION" | grep -qE 'CP_USER="(www-data|nginx)"'; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 17: nginx control panel config uses cp-panel PHP-FPM socket
echo -n "Test 17: nginx config uses cp-panel PHP-FPM socket... "
if grep -q "php8.3-fpm-cp-panel.sock" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

# Test 18: sudoers includes PHP-FPM pool.d directory rules
echo -n "Test 18: sudoers includes PHP-FPM pool management rules... "
if grep -q "fpm/pool.d" "$SCRIPT_DIR/install.sh"; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    TEST_RESULTS=$((TEST_RESULTS + 1))
fi

echo ""
echo "================================="
echo "Test Summary"
echo "================================="

if [[ $TEST_RESULTS -eq 0 ]]; then
    echo -e "${GREEN}All tests passed!${NC}"
    echo ""
    echo "The install.sh script is ready for use."
    echo "Run it with: sudo ./install.sh"
    exit 0
else
    echo -e "${RED}$TEST_RESULTS test(s) failed${NC}"
    echo ""
    echo "Please fix the issues before using the script."
    exit 1
fi
