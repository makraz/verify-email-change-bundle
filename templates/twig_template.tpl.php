<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Email Address</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .gradient-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .gradient-button:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <!-- Header -->
            <div class="gradient-header px-6 py-8 text-white">
                <h1 class="text-3xl font-bold mb-2">Change Email Address</h1>
                <p class="text-blue-100">Update the email address associated with your account</p>
            </div>

            <div class="p-6">
                <!-- Success Flash Messages -->
                {% for message in app.flashes('success') %}
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">{{ message }}</p>
                            </div>
                        </div>
                    </div>
                {% endfor %}

                <!-- Error Flash Messages -->
                {% for message in app.flashes('error') %}
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800">{{ message }}</p>
                            </div>
                        </div>
                    </div>
                {% endfor %}

                <!-- Pending Email Change Notice -->
                {% if app.user.pendingEmail %}
                    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 mb-6 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <h3 class="text-sm font-medium text-yellow-800">Pending Email Change</h3>
                                <p class="mt-1 text-sm text-yellow-700">
                                    You have a pending email change to <strong>{{ app.user.pendingEmail }}</strong>.
                                    Check your inbox to verify the new email address.
                                </p>
                                <form method="post" action="{{ path('app_email_change_cancel') }}" class="mt-3">
                                    <button type="submit" class="text-sm text-yellow-800 underline hover:text-yellow-900 font-medium">
                                        Cancel pending change
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                {% endif %}

                <!-- Email Change Form -->
                <form method="post" action="{{ path('app_email_change_request') }}" class="space-y-6">
                    <!-- Current Email (Read-only) -->
                    <div>
                        <label for="current_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Current Email Address
                        </label>
                        <input
                            type="email"
                            id="current_email"
                            value="{{ app.user.email }}"
                            disabled
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed"
                        >
                    </div>

                    <!-- New Email Input -->
                    <div>
                        <label for="new_email" class="block text-sm font-medium text-gray-700 mb-2">
                            New Email Address <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="email"
                            id="new_email"
                            name="new_email"
                            required
                            placeholder="your.new.email@example.com"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        >
                        <p class="mt-2 text-sm text-gray-600">
                            =ç A verification email will be sent to this address
                        </p>
                    </div>

                    <!-- Password Confirmation -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirm Your Password <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            placeholder="Enter your current password"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                        >
                        <p class="mt-2 text-sm text-gray-600">
                            = For security, please confirm your current password
                        </p>
                    </div>

                    <!-- Process Explanation -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-sm font-semibold text-blue-900 mb-2">=Ë What happens next?</h3>
                        <ol class="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                            <li>We'll send a verification email to your new address</li>
                            <li>Click the link in that email to confirm the change</li>
                            <li>Once verified, your email will be updated automatically</li>
                            <li>We'll notify your old email address about the change</li>
                        </ol>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <button
                            type="submit"
                            class="flex-1 gradient-button text-white font-semibold py-3 px-6 rounded-lg transition duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                        >
                            =è Send Verification Email
                        </button>
                        <a
                            href="{{ path('app_profile') }}"
                            class="px-6 py-3 border-2 border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition duration-200 text-center"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Security Tips Section -->
        <div class="mt-8 bg-white rounded-lg p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                <svg class="h-5 w-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Security Tips
            </h2>
            <ul class="space-y-3 text-sm text-gray-700">
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Use an email address you have access to and check regularly</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>Make sure your new email account is secure with a strong password</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>The verification link will expire after 1 hour for security reasons</span>
                </li>
                <li class="flex items-start">
                    <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>You can only have one pending email change at a time</span>
                </li>
            </ul>
        </div>
    </div>
</body>
</html>
