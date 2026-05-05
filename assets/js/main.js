(function ($) {
    "use strict";

    // Spinner
    var spinner = function () {
        setTimeout(function () {
            if ($('#spinner').length > 0) {
                $('#spinner').removeClass('show');
            }
        }, 1);
    };
    spinner(0);
    
    
    // Initiate the wowjs
    new WOW().init();


    // Sticky Navbar
    $(window).scroll(function () {
        if ($(this).scrollTop() > 45) {
            $('.nav-bar').addClass('sticky-top shadow-sm');
        } else {
            $('.nav-bar').removeClass('sticky-top shadow-sm');
        }
    });


    // Hero Header carousel
    $(".header-carousel").owlCarousel({
        items: 1,
        autoplay: true,
        smartSpeed: 2000,
        center: false,
        dots: false,
        loop: true,
        margin: 0,
        nav : true,
        navText : [
            '<i class="bi bi-arrow-left"></i>',
            '<i class="bi bi-arrow-right"></i>'
        ]
    });


    // ProductList carousel
    $(".productList-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 2000,
        dots: false,
        loop: true,
        margin: 25,
        nav : true,
        navText : [
            '<i class="fas fa-chevron-left"></i>',
            '<i class="fas fa-chevron-right"></i>'
        ],
        responsiveClass: true,
        responsive: {
            0:{
                items:1
            },
            576:{
                items:1
            },
            768:{
                items:2
            },
            992:{
                items:2
            },
            1200:{
                items:3
            }
        }
    });

    // ProductList categories carousel
    $(".productImg-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1500,
        dots: false,
        loop: true,
        items: 1,
        margin: 25,
        nav : true,
        navText : [
            '<i class="bi bi-arrow-left"></i>',
            '<i class="bi bi-arrow-right"></i>'
        ]
    });


    // Single Products carousel
    $(".single-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1500,
        dots: true,
        dotsData: true,
        loop: true,
        items: 1,
        nav : true,
        navText : [
            '<i class="bi bi-arrow-left"></i>',
            '<i class="bi bi-arrow-right"></i>'
        ]
    });


    // ProductList carousel
    $(".related-carousel").owlCarousel({
        autoplay: true,
        smartSpeed: 1500,
        dots: false,
        loop: true,
        margin: 25,
        nav : true,
        navText : [
            '<i class="fas fa-chevron-left"></i>',
            '<i class="fas fa-chevron-right"></i>'
        ],
        responsiveClass: true,
        responsive: {
            0:{
                items:1
            },
            576:{
                items:1
            },
            768:{
                items:2
            },
            992:{
                items:3
            },
            1200:{
                items:4
            }
        }
    });



    // Product Quantity
    $('.quantity button').on('click', function () {
        var button = $(this);
        var oldValue = button.parent().parent().find('input').val();
        if (button.hasClass('btn-plus')) {
            var newVal = parseFloat(oldValue) + 1;
        } else {
            if (oldValue > 0) {
                var newVal = parseFloat(oldValue) - 1;
            } else {
                newVal = 0;
            }
        }
        button.parent().parent().find('input').val(newVal);
    });


    
   // Back to top button
   $(window).scroll(function () {
    if ($(this).scrollTop() > 300) {
        $('.back-to-top').fadeIn('slow');
    } else {
        $('.back-to-top').fadeOut('slow');
    }
    });
    $('.back-to-top').click(function () {
        $('html, body').animate({scrollTop: 0}, 1500, 'easeInOutExpo');
        return false;
    });


   // Theme switcher
    const themeButtons = document.querySelectorAll('.theme-btn');
    const savedTheme = localStorage.getItem('theme') || 'default';
    const themeVars = {
        ocean: {
            '--bs-primary': '#0d6efd',
            '--bs-primary-rgb': '13, 110, 253',
            '--bs-secondary': '#20c997',
            '--bs-secondary-rgb': '32, 201, 151',
            '--bs-dark': '#0b132b',
            '--bs-dark-rgb': '11, 19, 43',
            '--bs-light': '#e7f1ff',
            '--bs-light-rgb': '231, 241, 255'
        },
        sunset: {
            '--bs-primary': '#ff6b6b',
            '--bs-primary-rgb': '255, 107, 107',
            '--bs-secondary': '#f7b267',
            '--bs-secondary-rgb': '247, 178, 103',
            '--bs-dark': '#3d2c2e',
            '--bs-dark-rgb': '61, 44, 46',
            '--bs-light': '#fff3e6',
            '--bs-light-rgb': '255, 243, 230'
        },
        forest: {
            '--bs-primary': '#2d6a4f',
            '--bs-primary-rgb': '45, 106, 79',
            '--bs-secondary': '#52b788',
            '--bs-secondary-rgb': '82, 183, 136',
            '--bs-dark': '#1b4332',
            '--bs-dark-rgb': '27, 67, 50',
            '--bs-light': '#e9f5ee',
            '--bs-light-rgb': '233, 245, 238'
        }
    };
    const themeKeys = [
        '--bs-primary',
        '--bs-primary-rgb',
        '--bs-secondary',
        '--bs-secondary-rgb',
        '--bs-dark',
        '--bs-dark-rgb',
        '--bs-light',
        '--bs-light-rgb'
    ];

    const applyTheme = (theme) => {
        const vars = themeVars[theme];
        if (!vars) {
            document.documentElement.removeAttribute('data-theme');
            themeKeys.forEach((key) => document.documentElement.style.removeProperty(key));
        } else {
            document.documentElement.setAttribute('data-theme', theme);
            Object.entries(vars).forEach(([key, value]) => {
                document.documentElement.style.setProperty(key, value);
            });
        }
        localStorage.setItem('theme', theme);
        themeButtons.forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.theme === theme);
        });
    };

    applyTheme(savedTheme);
    themeButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            applyTheme(btn.dataset.theme || 'default');
        });
    });

    document.addEventListener('click', (event) => {
        const target = event.target.closest('.theme-btn');
        if (!target) {
            return;
        }
        applyTheme(target.dataset.theme || 'default');
    });


   

})(jQuery);

