<?php
/**
 * Response templates for each intent.
 *
 * Some intents (like "greeting", "application_status", "notifications"
 * and "profile") are also handled with extra PHP logic in chatbot.php
 * because their reply depends on the logged-in student's name or their
 * data in the database. For those, this file is not used directly, but
 * is kept here for reference and for the simpler intents below.
 *
 * Every intent has several templates — one is picked at random each
 * time, so the bot doesn't sound like it's reading from a script.
 */

return [

    "goodbye" => [
        "Goodbye {name}! 👋 Good luck with your internship search.",
        "See you later, {name}! Come back anytime you have a question.",
        "Bye {name}! Wishing you all the best. 🍀",
    ],

    "thanks" => [
        "You're welcome, {name}! 😊 Good luck with your internship search!",
        "Anytime, {name}! Happy to help.",
        "No problem at all! Let me know if anything else comes up.",
        "Glad I could help! 🙌",
    ],

    "browse_internship" => [
        "You can find available internships from <b>Browse Internships</b>. 🔍",
        "Head over to <b>Browse Internships</b> in your dashboard to see all currently open positions. 🔍",
        "Sure! Open <b>Browse Internships</b> from the sidebar — new ones get posted there regularly, so it's worth checking often. 🔍",
    ],

    "apply_internship" => [
        "To apply, go to <b>Browse Internships</b>, pick one that interests you and click <b>Apply Now</b>. 📄",
        "Applying is easy: open <b>Browse Internships</b>, choose one that fits you, and hit <b>Apply Now</b>. 📄",
        "Sure — head to <b>Browse Internships</b>, find something you like, and click <b>Apply Now</b> to submit your application. 📄",
    ],

    "resume" => [
        "You can upload or update your resume from <b>My Profile</b>. 📑",
        "Go to <b>My Profile</b> to upload a new resume or replace your existing one. 📑",
        "Your resume lives in <b>My Profile</b> — you can swap it out anytime a new version is ready. 📑",
    ],

    "mock_test" => [
        "You can attend internship-related mock tests from the <b>Mock Tests</b> section. 📝",
        "Head to <b>Mock Tests</b> to practice, and check <b>Test Scores</b> afterwards to see how you did. 📝",
        "Practising helps a lot — try the <b>Mock Tests</b> section, then review your <b>Test Scores</b> to track progress. 📝",
    ],

    "profile" => [
        "Go to <b>My Profile</b> to update your education, skills and resume. 👤",
        "You can edit your education, skills, resume and contact details anytime from <b>My Profile</b>. 👤",
        "A complete profile really helps — <b>My Profile</b> is where you can fill in your skills and education. 👤",
    ],

    "notifications" => [
        "Check the <b>Notifications</b> section for your latest updates. 🔔",
        "Anything new — status changes, messages from companies — will show up under <b>Notifications</b>. 🔔",
    ],

    "help" => [
        "I can help you with:<br>• Browsing internships<br>• Applying for internships<br>• Checking application status<br>• Resume &amp; profile updates<br>• Mock tests<br>• Suggesting internships that match your skills<br><br>Just ask me in your own words — no need for exact commands!",
    ],

    "company_comparison" => [
        "I can compare companies based on the internships listed here, your skill match, and the roles they offer.",
    ],

    "small_talk" => [
        "I'm doing well, thanks for asking! 😊 I'm your Internship Assistant — I'm here to help with anything related to your internship search. What's on your mind?",
        "I'm an AI assistant built specifically for this platform — think of me as your internship-search sidekick. I can't chat about everything, but I know this app inside out! 🤖",
        "Good, thanks! I'm here whenever you need help with internships, applications, your resume, or mock tests — just ask.",
        "I'm a chatbot, so no coffee breaks needed! 😄 Happy to help with anything internship-related.",
    ],

    "encouragement" => [
        "That's completely normal, {name} — a lot of students feel this way. Take it one application at a time, and remember that every application is practice for the next. You've got this. 💪",
        "I hear you, {name}. Internship hunting can feel overwhelming, but a strong profile and some patience go a long way. Have you checked if your skills and resume are fully updated? That's often the biggest confidence booster.",
        "It's okay to feel nervous, {name} — that just means you care about doing well. Focus on what you can control: your profile, your applications, and practicing with the mock tests. The rest will follow. 🌱",
        "Totally understandable, {name}. Try not to compare your journey to others — keep applying, keep learning, and results will come. I'm here if you want help finding the right internships to apply to.",
    ],

    "fallback" => [
        "I’m not sure I understood that. 🤔<br><br>Try asking me something like:<br>• <b>Which internship suits my skills?</b><br>• <b>Which company is better for me?</b><br>• <b>What is my application status?</b><br>• <b>How do I apply?</b><br>• <b>How do I update my resume?</b>",
        "I may have misunderstood the question. Could you rephrase it? I can help with internships, skill matching, company comparisons, applications, application status, resumes, profiles and mock tests.",
    ],

];
