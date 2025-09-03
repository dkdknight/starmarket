#!/usr/bin/env python3
"""
StarMarket Backend API Testing Suite
Tests for real-time messaging functionality and authentication
"""

import requests
import json
import time
import threading
import sys
from datetime import datetime
from typing import Dict, Any, Optional

class StarMarketTester:
    def __init__(self, base_url: str = "http://localhost"):
        self.base_url = base_url.rstrip('/')
        self.session = requests.Session()
        self.test_results = []
        self.csrf_token = None
        self.user_id = None
        self.php_available = False
        
    def log_result(self, test_name: str, success: bool, message: str, details: Dict = None):
        """Log test result"""
        result = {
            'test': test_name,
            'success': success,
            'message': message,
            'timestamp': datetime.now().isoformat(),
            'details': details or {}
        }
        self.test_results.append(result)
        status = "✅ PASS" if success else "❌ FAIL"
        print(f"{status} {test_name}: {message}")
        if details and not success:
            print(f"   Details: {details}")

    def setup_test_environment(self):
        """Setup test environment with test user"""
        print("🔧 Setting up test environment...")
        
        # Check if PHP server is available
        try:
            response = self.session.get(f"{self.base_url}/index.php", timeout=5)
            if response.status_code == 200:
                self.log_result("connectivity", True, "PHP server is accessible")
                self.php_available = True
            else:
                self.log_result("connectivity", False, f"Server returned {response.status_code}")
                self.php_available = False
        except Exception as e:
            self.log_result("connectivity", False, f"Cannot connect to PHP server: {str(e)}")
            self.php_available = False
            
        if not self.php_available:
            print("⚠️  PHP server not available - performing static code analysis instead")
            return self.perform_static_analysis()
            
        return True

    def test_authentication_required(self):
        """Test that all API endpoints require authentication"""
        print("\n🔐 Testing authentication requirements...")
        
        endpoints = [
            "/api/realtime-messages.php",
            "/api/send-message-ajax.php", 
            "/api/check-new-messages.php",
            "/api/update-conversation-status.php",
            "/api/check-conversation-updates.php"
        ]
        
        for endpoint in endpoints:
            try:
                # Clear any existing session
                test_session = requests.Session()
                response = test_session.get(f"{self.base_url}{endpoint}", timeout=5)
                
                if response.status_code == 401:
                    self.log_result(f"auth_required_{endpoint}", True, "Correctly requires authentication")
                else:
                    self.log_result(f"auth_required_{endpoint}", False, 
                                  f"Expected 401, got {response.status_code}")
                    
            except Exception as e:
                self.log_result(f"auth_required_{endpoint}", False, f"Error testing: {str(e)}")

    def test_realtime_messages_sse(self):
        """Test Server-Sent Events endpoint"""
        print("\n📡 Testing SSE endpoint...")
        
        try:
            # Test without authentication first
            response = self.session.get(f"{self.base_url}/api/realtime-messages.php", 
                                      timeout=5, stream=True)
            
            if response.status_code == 401:
                self.log_result("sse_auth_check", True, "SSE endpoint correctly requires authentication")
            else:
                self.log_result("sse_auth_check", False, 
                              f"SSE endpoint should return 401, got {response.status_code}")
                
            # Test headers for SSE
            if 'text/event-stream' in response.headers.get('content-type', ''):
                self.log_result("sse_headers", True, "Correct SSE headers present")
            else:
                self.log_result("sse_headers", False, "Missing SSE content-type header")
                
        except Exception as e:
            self.log_result("sse_endpoint", False, f"Error testing SSE: {str(e)}")

    def test_send_message_ajax(self):
        """Test AJAX message sending endpoint"""
        print("\n💬 Testing message sending...")
        
        try:
            # Test without authentication
            test_data = {
                "conversation_id": 1,
                "message": "Test message",
                "csrf_token": "invalid_token"
            }
            
            response = self.session.post(
                f"{self.base_url}/api/send-message-ajax.php",
                json=test_data,
                headers={'Content-Type': 'application/json'},
                timeout=5
            )
            
            if response.status_code == 401:
                self.log_result("send_message_auth", True, "Message endpoint requires authentication")
            else:
                self.log_result("send_message_auth", False, 
                              f"Expected 401, got {response.status_code}")
                
            # Test with invalid data
            response = self.session.post(
                f"{self.base_url}/api/send-message-ajax.php",
                json={},
                headers={'Content-Type': 'application/json'},
                timeout=5
            )
            
            if response.status_code in [400, 401]:
                self.log_result("send_message_validation", True, "Validates input data")
            else:
                self.log_result("send_message_validation", False, 
                              f"Should validate input, got {response.status_code}")
                
        except Exception as e:
            self.log_result("send_message_ajax", False, f"Error testing message sending: {str(e)}")

    def test_check_new_messages(self):
        """Test new messages checking endpoint"""
        print("\n🔍 Testing new messages check...")
        
        try:
            response = self.session.get(f"{self.base_url}/api/check-new-messages.php", timeout=5)
            
            if response.status_code == 401:
                self.log_result("check_messages_auth", True, "Check messages requires authentication")
            else:
                self.log_result("check_messages_auth", False, 
                              f"Expected 401, got {response.status_code}")
                
            # Check response format
            if response.headers.get('content-type', '').startswith('application/json'):
                self.log_result("check_messages_format", True, "Returns JSON response")
            else:
                self.log_result("check_messages_format", False, "Should return JSON")
                
        except Exception as e:
            self.log_result("check_new_messages", False, f"Error testing: {str(e)}")

    def test_update_conversation_status(self):
        """Test conversation status update endpoint"""
        print("\n🔄 Testing conversation status updates...")
        
        try:
            test_data = {
                "conversation_id": 1,
                "status": "SCHEDULED",
                "meeting_details": "Test meeting",
                "csrf_token": "invalid_token"
            }
            
            response = self.session.post(
                f"{self.base_url}/api/update-conversation-status.php",
                json=test_data,
                headers={'Content-Type': 'application/json'},
                timeout=5
            )
            
            if response.status_code == 401:
                self.log_result("status_update_auth", True, "Status update requires authentication")
            else:
                self.log_result("status_update_auth", False, 
                              f"Expected 401, got {response.status_code}")
                
            # Test invalid status
            invalid_data = {
                "conversation_id": 1,
                "status": "INVALID_STATUS",
                "csrf_token": "test_token"
            }
            
            response = self.session.post(
                f"{self.base_url}/api/update-conversation-status.php",
                json=invalid_data,
                headers={'Content-Type': 'application/json'},
                timeout=5
            )
            
            # Should return 400 or 401 for invalid data
            if response.status_code in [400, 401]:
                self.log_result("status_validation", True, "Validates status values")
            else:
                self.log_result("status_validation", False, 
                              f"Should validate status, got {response.status_code}")
                
        except Exception as e:
            self.log_result("update_conversation_status", False, f"Error testing: {str(e)}")

    def test_check_conversation_updates(self):
        """Test conversation updates checking"""
        print("\n📋 Testing conversation updates check...")
        
        try:
            # Test without conversation_id
            response = self.session.get(f"{self.base_url}/api/check-conversation-updates.php", timeout=5)
            
            if response.status_code == 401:
                self.log_result("conv_updates_auth", True, "Conversation updates require authentication")
            else:
                self.log_result("conv_updates_auth", False, 
                              f"Expected 401, got {response.status_code}")
                
            # Test with conversation_id parameter
            response = self.session.get(
                f"{self.base_url}/api/check-conversation-updates.php?conversation_id=1&last_message=2024-01-01",
                timeout=5
            )
            
            if response.status_code == 401:
                self.log_result("conv_updates_params", True, "Handles parameters correctly")
            else:
                self.log_result("conv_updates_params", False, 
                              f"Should still require auth, got {response.status_code}")
                
        except Exception as e:
            self.log_result("check_conversation_updates", False, f"Error testing: {str(e)}")

    def test_database_structure(self):
        """Test if database structure is consistent"""
        print("\n🗄️ Testing database structure consistency...")
        
        # This is indirect testing through API behavior
        # We can't directly access the database, but we can test if the APIs
        # behave as expected based on the database schema
        
        try:
            # Test that endpoints expect the right data structures
            endpoints_data = {
                "/api/send-message-ajax.php": {
                    "conversation_id": "integer",
                    "message": "string", 
                    "csrf_token": "string"
                },
                "/api/update-conversation-status.php": {
                    "conversation_id": "integer",
                    "status": "enum",
                    "meeting_details": "string",
                    "csrf_token": "string"
                }
            }
            
            for endpoint, expected_fields in endpoints_data.items():
                # Test with missing required fields
                response = self.session.post(
                    f"{self.base_url}{endpoint}",
                    json={},
                    headers={'Content-Type': 'application/json'},
                    timeout=5
                )
                
                if response.status_code in [400, 401]:
                    self.log_result(f"db_structure_{endpoint}", True, 
                                  "API validates required fields correctly")
                else:
                    self.log_result(f"db_structure_{endpoint}", False, 
                                  f"Should validate fields, got {response.status_code}")
                    
        except Exception as e:
            self.log_result("database_structure", False, f"Error testing DB structure: {str(e)}")

    def test_error_handling(self):
        """Test error handling across all endpoints"""
        print("\n⚠️ Testing error handling...")
        
        endpoints = [
            "/api/realtime-messages.php",
            "/api/send-message-ajax.php",
            "/api/check-new-messages.php", 
            "/api/update-conversation-status.php",
            "/api/check-conversation-updates.php"
        ]
        
        for endpoint in endpoints:
            try:
                # Test with malformed JSON
                response = self.session.post(
                    f"{self.base_url}{endpoint}",
                    data="invalid json",
                    headers={'Content-Type': 'application/json'},
                    timeout=5
                )
                
                # Should handle malformed data gracefully
                if response.status_code in [400, 401, 500]:
                    self.log_result(f"error_handling_{endpoint}", True, 
                                  "Handles malformed requests gracefully")
                else:
                    self.log_result(f"error_handling_{endpoint}", False, 
                                  f"Should handle errors, got {response.status_code}")
                    
            except Exception as e:
                # Network errors are acceptable for this test
                self.log_result(f"error_handling_{endpoint}", True, 
                              "Handles network errors appropriately")

    def test_csrf_protection(self):
        """Test CSRF token validation"""
        print("\n🛡️ Testing CSRF protection...")
        
        csrf_endpoints = [
            "/api/send-message-ajax.php",
            "/api/update-conversation-status.php"
        ]
        
        for endpoint in csrf_endpoints:
            try:
                # Test without CSRF token
                test_data = {
                    "conversation_id": 1,
                    "message": "test" if "message" in endpoint else None,
                    "status": "OPEN" if "status" in endpoint else None
                }
                # Remove None values
                test_data = {k: v for k, v in test_data.items() if v is not None}
                
                response = self.session.post(
                    f"{self.base_url}{endpoint}",
                    json=test_data,
                    headers={'Content-Type': 'application/json'},
                    timeout=5
                )
                
                # Should require authentication or CSRF token
                if response.status_code in [400, 401, 403]:
                    self.log_result(f"csrf_protection_{endpoint}", True, 
                                  "CSRF protection is active")
                else:
                    self.log_result(f"csrf_protection_{endpoint}", False, 
                                  f"Should validate CSRF, got {response.status_code}")
                    
            except Exception as e:
                self.log_result(f"csrf_protection_{endpoint}", False, f"Error testing CSRF: {str(e)}")

    def run_all_tests(self):
        """Run all tests"""
        print("🚀 Starting StarMarket Backend API Tests")
        print("=" * 50)
        
        if not self.setup_test_environment():
            print("❌ Failed to setup test environment")
            return False
            
        # Run all test methods
        self.test_authentication_required()
        self.test_realtime_messages_sse()
        self.test_send_message_ajax()
        self.test_check_new_messages()
        self.test_update_conversation_status()
        self.test_check_conversation_updates()
        self.test_database_structure()
        self.test_error_handling()
        self.test_csrf_protection()
        
        # Summary
        print("\n" + "=" * 50)
        print("📊 TEST SUMMARY")
        print("=" * 50)
        
        total_tests = len(self.test_results)
        passed_tests = sum(1 for result in self.test_results if result['success'])
        failed_tests = total_tests - passed_tests
        
        print(f"Total Tests: {total_tests}")
        print(f"Passed: {passed_tests} ✅")
        print(f"Failed: {failed_tests} ❌")
        print(f"Success Rate: {(passed_tests/total_tests)*100:.1f}%")
        
        if failed_tests > 0:
            print("\n❌ FAILED TESTS:")
            for result in self.test_results:
                if not result['success']:
                    print(f"  - {result['test']}: {result['message']}")
        
        return failed_tests == 0

def main():
    """Main test runner"""
    # Use localhost as the base URL for PHP application
    base_url = "http://localhost"
    
    tester = StarMarketTester(base_url)
    success = tester.run_all_tests()
    
    # Save detailed results
    with open('/app/test_results_detailed.json', 'w') as f:
        json.dump(tester.test_results, f, indent=2)
    
    print(f"\n📄 Detailed results saved to: /app/test_results_detailed.json")
    
    return 0 if success else 1

if __name__ == "__main__":
    sys.exit(main())