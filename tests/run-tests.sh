#!/bin/bash
#
# Media Lab Starter Kit - Test Runner
#

set -e  # Exit on error

echo "════════════════════════════════════════════════════════"
echo "🧪 Media Lab Starter Kit - Test Suite"
echo "════════════════════════════════════════════════════════"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

# Test function
run_test() {
    local test_name=$1
    local test_command=$2
    
    echo -n "Testing: $test_name... "
    
    if eval "$test_command" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ PASS${NC}"
        ((PASSED++))
        return 0
    else
        echo -e "${RED}❌ FAIL${NC}"
        ((FAILED++))
        return 1
    fi
}

# Change to CMS directory
cd "$(dirname "$0")/../cms"

echo "Running Smoke Tests..."
echo "────────────────────────────────────────────────────────"
echo ""

# Plugin Tests
echo "📦 Plugin Tests:"
run_test "Core Plugin Active" "wp plugin is-active media-lab-agency-core"
run_test "Project Plugin Active" "wp plugin is-active media-lab-project-starter"

# Shortcode Tests
echo ""
echo "🔖 Shortcode Tests:"
run_test "Accordion Shortcode" "wp eval 'global \$shortcode_tags; exit(isset(\$shortcode_tags[\"accordion\"]) ? 0 : 1);'"
run_test "Hero Slider Shortcode" "wp eval 'global \$shortcode_tags; exit(isset(\$shortcode_tags[\"hero_slider\"]) ? 0 : 1);'"
run_test "Stats Shortcode" "wp eval 'global \$shortcode_tags; exit(isset(\$shortcode_tags[\"stats\"]) ? 0 : 1);'"
run_test "Modal Shortcode" "wp eval 'global \$shortcode_tags; exit(isset(\$shortcode_tags[\"modal\"]) ? 0 : 1);'"

# CPT Tests
echo ""
echo "📋 Custom Post Type Tests:"
run_test "Team CPT Registered" "wp post-type list --format=ids | grep -q team"
run_test "Project CPT Registered" "wp post-type list --format=ids | grep -q project"
run_test "Job CPT Registered" "wp post-type list --format=ids | grep -q job"

# ACF Tests
echo ""
echo "🎨 ACF Tests:"
run_test "ACF Active" "wp plugin is-active advanced-custom-fields-pro"
run_test "ACF Field Groups Loaded" "wp eval 'exit(count(acf_get_field_groups()) >= 11 ? 0 : 1);'"
run_test "ACF JSON Source" "wp eval '\$g = acf_get_field_groups(); exit(isset(\$g[0][\"local\"]) && \$g[0][\"local\"] === \"json\" ? 0 : 1);'"

# Theme Tests
echo ""
echo "🎨 Theme Tests:"
run_test "Custom Theme Active" "wp theme is-active custom-theme"

# AJAX Tests
echo ""
echo "⚡ AJAX Tests:"
run_test "AJAX Search Action" "wp eval 'exit(has_action(\"wp_ajax_agency_search\") ? 0 : 1);'"
run_test "AJAX Load More Action" "wp eval 'exit(has_action(\"wp_ajax_agency_load_more\") ? 0 : 1);'"
run_test "AJAX Filter Action" "wp eval 'exit(has_action(\"wp_ajax_ajax_filter_posts\") ? 0 : 1);'"

# Summary
echo ""
echo "════════════════════════════════════════════════════════"
echo "📊 Test Results"
echo "════════════════════════════════════════════════════════"
echo ""
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo "Total:  $((PASSED + FAILED))"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed!${NC}"
    exit 1
fi
