document.addEventListener("DOMContentLoaded", () => {

    const loginForm = document.getElementById("loginForm");
    if (!loginForm) return;

    loginForm.addEventListener("submit", async (e) => {
        e.preventDefault(); // stop blade form submit

        const email = loginForm.querySelector('input[name="email"]').value.trim();
        const password = loginForm.querySelector('input[name="password"]').value.trim();

        if (!email || !password) {
            alert("Email and password are required");
            return;
        }

        const formData = new FormData();
        formData.append("email", email);
        formData.append("password", password);

        try {
            const res = await fetch(domainUrl + "partner/login", {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content")
                },
                body: formData
            });

            const data = await res.json();

            if (!res.ok || data.success === false) {
                alert(data.message || "Login failed");
                return;
            }

            // ✅ LOGIN SUCCESS
            alert("Login successful");

            // redirect to dashboard
            window.location.href = domainUrl + "partner/dashboard";

        } catch (err) {
            console.error("Login error:", err);
            alert("Something went wrong. Please try again.");
        }
    });

});
