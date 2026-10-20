document.addEventListener("DOMContentLoaded", function () {

    const menuToggle =
        document.getElementById("menuToggle");

    const navMenu =
        document.getElementById("navMenu");


    if (menuToggle && navMenu) {

        menuToggle.addEventListener(
            "click",
            function () {

                navMenu.classList.toggle("show");

            }
        );


        const links =
            navMenu.querySelectorAll("a");


        links.forEach(function (link) {

            link.addEventListener(
                "click",
                function () {

                    navMenu.classList.remove("show");

                }
            );

        });

    }

    const revealElements =
        document.querySelectorAll(
            ".story-content, " +
            ".featured-card, " +
            ".category-card, " +
            ".product-card, " +
            ".custom-container, " +
            ".gallery-item, " +
            ".contact-box"
        );


    const observer =
        new IntersectionObserver(
            function (entries) {

                entries.forEach(
                    function (entry) {

                        if (
                            entry.isIntersecting
                        ) {

                            entry.target.classList.add(
                                "show"
                            );

                        }

                    }
                );

            },
            {
                threshold: 0.12
            }
        );


    revealElements.forEach(
        function (element) {

            element.classList.add(
                "reveal"
            );

            observer.observe(element);

        }
    );

    document.addEventListener(
        "click",
        function (event) {

            if (
                navMenu &&
                menuToggle &&
                navMenu.classList.contains("show")
            ) {

                if (
                    !navMenu.contains(event.target) &&
                    !menuToggle.contains(event.target)
                ) {

                    navMenu.classList.remove(
                        "show"
                    );

                }

            }

        }
    );

});