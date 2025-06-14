<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Password Hash Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px;
            min-height: 100vh;
            margin: 0;
        }
        form {
            background: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
            width: 320px;
            box-sizing: border-box;
        }
        label {
            font-weight: bold;
            margin-bottom: 8px;
            display: block;
        }
        input[type="password"] {
            width: 100%;
            padding: 8px 10px;
            margin-bottom: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="password"]:focus {
            border-color: #007bff;
            outline: none;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            border: none;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #0056b3;
        }
        #progressContainer {
            margin: 20px 0;
            width: 320px;
            height: 20px;
            background-color: #ddd;
            border-radius: 10px;
            overflow: hidden;
            display: none;
        }
        #progressBar {
            height: 100%;
            width: 0%;
            background-color: red;
            transition: width 0.2s ease, background-color 0.5s ease;
        }
        #progressText {
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
            color: #333;
        }
        #hashResult {
            background: #eef6fc;
            border: 1px solid #007bff;
            word-break: break-all;
            padding: 10px;
            border-radius: 5px;
            width: 320px;
            box-sizing: border-box;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <form id="passwordForm" method="post" autocomplete="off">
        <label for="pwd">Enter a password:</label>
        <input type="password" id="pwd" name="pwd" required minlength="4" />
        <button type="submit">Generate Hash</button>
    </form>

    <div id="progressContainer">
        <div id="progressText">Progress: 0%</div>
        <div id="progressBar"></div>
    </div>

    <?php
    $hash = "";
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['pwd'])) {
        $password = $_POST['pwd'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
    ?>

    <div id="hashResult" style="<?php echo $hash ? '' : 'display:none' ?>">
        <?php echo $hash ? "Generated hash:<br><code>" . htmlspecialchars($hash) . "</code>" : '' ?>
    </div>

    <script>
    const form = document.getElementById('passwordForm');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const hashResult = document.getElementById('hashResult');

    // Calculate "complexity": length + character types (upper, lower, digits, special)
    function passwordComplexity(pwd) {
        let complexity = 0;
        const length = pwd.length;

        if (length >= 4) complexity += 1;
        if (length >= 8) complexity += 1;
        if (length >= 12) complexity += 1;

        if (/[A-Z]/.test(pwd)) complexity += 1;
        if (/[a-z]/.test(pwd)) complexity += 1;
        if (/\d/.test(pwd)) complexity += 1;
        if (/[\W_]/.test(pwd)) complexity += 1;

        return complexity; // max 7
    }

    form.addEventListener('submit', e => {
        e.preventDefault();

        const pwd = form.pwd.value.trim();
        if (pwd.length < 4) {
            alert("Password too short (min 4 characters).");
            return;
        }

        const comp = passwordComplexity(pwd);

        const minTime = 5000; // 5s
        const maxTime = 10000; // 10s

        const animTime = minTime + ((comp - 1) / 6) * (maxTime - minTime);

        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressBar.style.backgroundColor = 'red';
        progressText.textContent = 'Progress: 0%';
        hashResult.style.display = 'none';

        let start = null;
        function step(timestamp) {
            if (!start) start = timestamp;
            const elapsed = timestamp - start;

            let progress = Math.min(elapsed / animTime, 1);
            let percent = Math.floor(progress * 100);
            progressBar.style.width = percent + '%';

            if (percent <= 25) {
                progressBar.style.backgroundColor = 'red';
            } else if (percent <= 50) {
                progressBar.style.backgroundColor = 'yellow';
            } else if (percent <= 75) {
                progressBar.style.backgroundColor = 'blue';
            } else {
                progressBar.style.backgroundColor = 'green';
            }

            progressText.textContent = 'Progress: ' + percent + '%';

            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                const formData = new FormData(form);
                fetch("", {
                    method: "POST",
                    body: formData,
                })
                .then(res => res.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, "text/html");
                    const resultDiv = doc.getElementById('hashResult');
                    if (resultDiv) {
                        hashResult.innerHTML = resultDiv.innerHTML;
                        hashResult.style.display = 'block';
                    }
                })
                .catch(err => {
                    alert("Error generating the hash.");
                    console.error(err);
                });
            }
        }

        window.requestAnimationFrame(step);
    });
    </script>
</body>
</html>