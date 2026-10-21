<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Digital Internship Portal</title>

<style>

/* RESET */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}


/* BODY */

body {
    font-family: Arial, sans-serif;
    background: #f5f7ff;
    color: #333;
}


/* NAVBAR */

.navbar {
    width: 100%;
    padding: 18px 7%;
    background: white;

    display: flex;
    justify-content: space-between;
    align-items: center;

    box-shadow: 0 3px 15px rgba(0,0,0,0.08);
}

.logo {
    font-size: 23px;
    font-weight: bold;
    color: #667eea;
}

.nav-links {
    display: flex;
    gap: 12px;
}

.nav-links a {
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 7px;
    font-size: 14px;
    font-weight: bold;
}

.login-btn {
    color: #667eea;
    border: 1px solid #667eea;
}

.register-btn {
    background: #667eea;
    color: white;
}

.nav-links a:hover {
    opacity: 0.85;
}


/* HERO */

.hero {
    min-height: 550px;

    display: flex;
    justify-content: space-between;
    align-items: center;

    padding: 70px 8%;

    background:
        linear-gradient(
            135deg,
            #eef0ff,
            #f8f4ff
        );
}

.hero-content {
    max-width: 600px;
}

.hero-content h1 {
    font-size: 48px;
    line-height: 1.15;
    margin-bottom: 20px;
    color: #333;
}

.hero-content h1 span {
    color: #667eea;
}

.hero-content p {
    color: #666;
    font-size: 17px;
    line-height: 1.7;
    margin-bottom: 30px;
}

.hero-buttons {
    display: flex;
    gap: 15px;
}

.primary-btn {
    text-decoration: none;
    background: #667eea;
    color: white;

    padding: 14px 25px;
    border-radius: 8px;

    font-weight: bold;
}

.secondary-btn {
    text-decoration: none;
    background: white;
    color: #667eea;

    padding: 14px 25px;
    border-radius: 8px;

    border: 1px solid #667eea;
    font-weight: bold;
}

.primary-btn:hover,
.secondary-btn:hover {
    opacity: 0.85;
}


/* HERO ICON */

.hero-icon {
    width: 350px;
    height: 350px;

    border-radius: 50%;

    background: linear-gradient(
        135deg,
        #667eea,
        #764ba2
    );

    display: flex;
    justify-content: center;
    align-items: center;

    font-size: 130px;

    box-shadow:
        0 20px 50px rgba(102,126,234,0.3);
}


/* FEATURES */

.features {
    padding: 60px 8%;
    background: white;
}

.section-title {
    text-align: center;
    margin-bottom: 40px;
}

.section-title h2 {
    font-size: 30px;
    color: #333;
    margin-bottom: 10px;
}

.section-title p {
    color: #777;
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.feature-card {
    background: #f8f9ff;
    padding: 25px;

    border-radius: 12px;

    text-align: center;

    transition: 0.3s;
}

.feature-card:hover {
    transform: translateY(-7px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.feature-icon {
    font-size: 40px;
    margin-bottom: 15px;
}

.feature-card h3 {
    color: #444;
    margin-bottom: 10px;
}

.feature-card p {
    color: #777;
    font-size: 14px;
    line-height: 1.6;
}


/* HOW IT WORKS */

.how-it-works {
    padding: 60px 8%;
    background: #f5f7ff;
}

.steps {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}

.step {
    background: white;
    padding: 25px;

    border-radius: 12px;

    text-align: center;
}

.step-number {
    width: 45px;
    height: 45px;

    margin: auto;
    margin-bottom: 15px;

    border-radius: 50%;

    background: #667eea;
    color: white;

    display: flex;
    justify-content: center;
    align-items: center;

    font-weight: bold;
    font-size: 18px;
}

.step h3 {
    margin-bottom: 8px;
}

.step p {
    color: #777;
    font-size: 14px;
    line-height: 1.5;
}


/* CTA */

.cta {
    padding: 60px 20px;
    text-align: center;

    background: linear-gradient(
        135deg,
        #667eea,
        #764ba2
    );

    color: white;
}

.cta h2 {
    font-size: 30px;
    margin-bottom: 12px;
}

.cta p {
    margin-bottom: 25px;
}

.cta a {
    display: inline-block;

    background: white;
    color: #667eea;

    text-decoration: none;

    padding: 13px 25px;

    border-radius: 8px;

    font-weight: bold;
}


/* FOOTER */

footer {
    background: #222;
    color: #aaa;

    text-align: center;

    padding: 20px;

    font-size: 13px;
}


/* RESPONSIVE */

@media (max-width: 1000px) {

    .hero {
        flex-direction: column;
        text-align: center;
        gap: 50px;
    }

    .hero-buttons {
        justify-content: center;
    }

    .feature-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .steps {
        grid-template-columns: repeat(2, 1fr);
    }

}


@media (max-width: 600px) {

    .navbar {
        padding: 15px 5%;
    }

    .nav-links {
        gap: 5px;
    }

    .nav-links a {
        padding: 8px 10px;
        font-size: 12px;
    }

    .hero {
        padding: 50px 6%;
    }

    .hero-content h1 {
        font-size: 35px;
    }

    .hero-icon {
        width: 230px;
        height: 230px;
        font-size: 80px;
    }

    .feature-grid,
    .steps {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<!-- NAVBAR -->

<nav class="navbar">

    <div class="logo">
        🎓 Digital Internship
    </div>

    <div class="nav-links">

        <a href="login.php" class="login-btn">
            Login
        </a>

        <a href="register.php" class="register-btn">
            Register
        </a>

    </div>

</nav>



<!-- HERO -->

<section class="hero">

    <div class="hero-content">

        <h1>
            Find Your
            <span>Perfect Internship</span>
        </h1>

        <p>
            Digital Internship Portal helps users discover
            internship opportunities, match their skills,
            apply online, attend mock tests and track
            their applications in one place.
        </p>

        <div class="hero-buttons">

            <a
                href="register.php"
                class="primary-btn"
            >
                Get Started
            </a>

            <a
                href="login.php"
                class="secondary-btn"
            >
                Login
            </a>

        </div>

    </div>


    <div class="hero-icon">
        🎓
    </div>

</section>



<!-- FEATURES -->

<section class="features">

    <div class="section-title">

        <h2>
            Everything You Need
        </h2>

        <p>
            Manage your internship journey in one place.
        </p>

    </div>


    <div class="feature-grid">


        <div class="feature-card">

            <div class="feature-icon">
                🔍
            </div>

            <h3>
                Browse Internships
            </h3>

            <p>
                Explore available internship opportunities
                based on your interests and skills.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">
                🎯
            </div>

            <h3>
                Skill Matching
            </h3>

            <p>
                Find internships that match the skills
                listed in your profile.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">
                📝
            </div>

            <h3>
                Mock Tests
            </h3>

            <p>
                Attend internship-specific mock tests
                and improve your technical knowledge.
            </p>

        </div>


        <div class="feature-card">

            <div class="feature-icon">
                📊
            </div>

            <h3>
                Track Applications
            </h3>

            <p>
                Easily monitor your internship application
                status from your dashboard.
            </p>

        </div>


    </div>

</section>



<!-- HOW IT WORKS -->

<section class="how-it-works">

    <div class="section-title">

        <h2>
            How It Works
        </h2>

        <p>
            Start your internship journey in four simple steps.
        </p>

    </div>


    <div class="steps">


        <div class="step">

            <div class="step-number">
                1
            </div>

            <h3>
                Create Account
            </h3>

            <p>
                Create your account and get started
                with the Digital Internship Portal.
            </p>

        </div>


        <div class="step">

            <div class="step-number">
                2
            </div>

            <h3>
                Complete Profile
            </h3>

            <p>
                Add your education, skills and resume
                to your profile.
            </p>

        </div>


        <div class="step">

            <div class="step-number">
                3
            </div>

            <h3>
                Find & Apply
            </h3>

            <p>
                Browse suitable internships and
                apply online.
            </p>

        </div>


        <div class="step">

            <div class="step-number">
                4
            </div>

            <h3>
                Track Progress
            </h3>

            <p>
                Check your application status,
                mock test scores and updates.
            </p>

        </div>


    </div>

</section>



<!-- CTA -->

<section class="cta">

    <h2>
        Ready to Start Your Internship Journey?
    </h2>

    <p>
        Create your account and discover opportunities today.
    </p>

    <a href="register.php">
        Create Account
    </a>

</section>



<!-- FOOTER -->

<footer>

    © <?php echo date("Y"); ?>
    Digital Internship Portal.
    All Rights Reserved.

</footer>


</body>

</html>