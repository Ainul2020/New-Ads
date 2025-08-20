<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>অটো রিপ্লাই হেল্পলাইন সিস্টেম</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: #4CAF50;
        }

        select, input[type="text"], button {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        button {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }

        .result {
            margin-top: 20px;
            padding: 10px;
            background-color: #f4f4f4;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: none;
            min-height: 50px;
        }

        .error {
            color: red;
        }

        /* Menu Button Style */
        .menu-button {
            background-color: #2196F3;
            color: white;
            font-size: 16px;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            margin-top: 20px;
            cursor: pointer;
            text-align: center;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .menu-button:hover {
            background-color: #1976D2;
        }

        /* Animation */
        .typing {
            display: inline-block;
            border-right: 3px solid #4CAF50;
            padding-right: 5px;
            animation: typing 2s steps(30) 1s forwards, blink 0.75s step-end infinite;
        }

        @keyframes typing {
            from { width: 0; }
            to { width: 100%; }
        }

        @keyframes blink {
            50% { border-color: transparent; }
        }
    </style>
</head>
<body style="background: linear-gradient(270deg, #FF6699 33%, #FF6600 80%);">
<div style="background: #80deea;"
<div class="container">
    <h1>অটো রিপ্লাই হেল্পলাইন সিস্টেম</h1>
    
    <!-- Menu Button: Navigate to Dashboard -->
    <button class="menu-button" onclick="goToDashboard()">ড্যাশবোর্ডে যান</button>

    <label for="department">আপনার সমস্যার বিভাগ নির্বাচন করুন:</label>
    <select id="department">
        <option value="">-- নির্বাচন করুন --</option>
        <option value="account">একাউন্ট একটিভ হয় না</option>
        <option value="premium">প্রিমিয়াম সমস্যা</option>
    </select>

    <label for="issue">আপনার সমস্যা বা প্রশ্ন লিখুন:</label>
    <input type="text" id="issue" placeholder="আপনার সমস্যা বা প্রশ্ন লিখুন">

    <button onclick="handleIssue()">সাবমিট করুন</button>

    <div class="result" id="result"></div>
</div>

<script>
    // Function to navigate to the Dashboard
    function goToDashboard() {
        window.location.href = "/dashboard";  // Redirect to /dashboard
    }

    function handleIssue() {
        var department = document.getElementById("department").value;
        var issue = document.getElementById("issue").value.toLowerCase(); // Convert input to lowercase
        var resultDiv = document.getElementById("result");
        resultDiv.style.display = "none";  // Hide the result initially

        if (department === "" || issue === "") {
            resultDiv.innerHTML = "<p class='error'>অনুগ্রহ করে বিভাগ এবং আপনার সমস্যা উল্লেখ করুন।</p>";
            resultDiv.style.display = "block";
            return;
        }

        var response = "";

        if (department === "account") {
            if (issue.includes("একটিভ") || issue.includes("activate") || issue.includes("account")) {
                response = "আপনার একাউন্ট একটিভেশনের জন্য সহায়ক টিম শীঘ্রই যোগাযোগ করবে।";
            } else {
                response = "দুঃখিত, আমরা আপনার একাউন্টের সাথে সম্পর্কিত সমস্যা বুঝতে পারিনি।";
            }
        } else if (department === "premium") {
            if (issue.includes("প্রিমিয়াম") || issue.includes("premium")) {
                response = "প্রিমিয়াম সমস্যা সমাধানে সহায়ক টিম শীঘ্রই আপনার সাথে যোগাযোগ করবে।";
            } else {
                response = "দুঃখিত, আমরা প্রিমিয়াম সমস্যার সাথে সম্পর্কিত কোনো তথ্য খুঁজে পাইনি।";
            }
        }

        // Display response with animation (typing effect)
        resultDiv.style.display = "block";
        resultDiv.innerHTML = `<p class="typing">${response}</p>`;
    }
</script>

</body>
</html>
