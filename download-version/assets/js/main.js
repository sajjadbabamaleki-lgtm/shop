(function ($) {
    "use strict";
    /*=================================
        JS Index Here
    ==================================*/
    /*
    01. On Load Function
    02. Preloader
    03. Mobile Menu
    04. Sticky fix
    05. Scroll To Top
    06. Set Background Image Color & Mask
    07. Global Slider
    08. Slider Tab
    09. Ajax Contact Form
    10. Popup Sidemenu
    11. Magnific Popup
    12. Section Position
    13. Filter
    14. Counter Up
    15. Hover Item
    16. Shape Mockup
    17. Progress Bar Animation
    18. Countdown
    19. Hero 2 Animation
    20. Tilt Active
    21. Overlay Direction 
    00. Color Scheme
    00. Woocommerce Toggle
    00. Right Click Disable
    */
    /*=================================
        JS Index End
    ==================================*/
    /*

  /*---------- 01. On Load Function ----------*/
    $(window).on("load", function () {
        $(".preloader").fadeOut();
        $(".swiper-fade").addClass('fade-ani');
    });


    // $('select').niceSelect(); 
    if ($('.nice-select').length) {
        $('.nice-select').niceSelect();
    }

    /*---------- 02. Preloader ----------*/
    if ($(".preloader").length > 0) {
        $(".preloaderCls").each(function () {
            $(this).on("click", function (e) {
                e.preventDefault();
                $(".preloader").css("display", "none");
            });
        });
    }

    /*---------- 03. Mobile Menu ----------*/
    $.fn.thmobilemenu = function (options) {
        var opt = $.extend({
                menuToggleBtn: ".th-menu-toggle",
                bodyToggleClass: "th-body-visible",
                subMenuClass: "th-submenu",
                subMenuParent: "menu-item-has-children",
                thSubMenuParent: "th-item-has-children",
                subMenuParentToggle: "th-active",
                meanExpandClass: "th-mean-expand",
                appendElement: '<span class="th-mean-expand"></span>',
                subMenuToggleClass: "th-open",
                toggleSpeed: 400,
            },
            options
        );

        return this.each(function () {
            var menu = $(this); // Select menu

            // Menu Show & Hide
            function menuToggle() {
                menu.toggleClass(opt.bodyToggleClass);

                // collapse submenu on menu hide or show
                var subMenu = "." + opt.subMenuClass;
                $(subMenu).each(function () {
                    if ($(this).hasClass(opt.subMenuToggleClass)) {
                        $(this).removeClass(opt.subMenuToggleClass);
                        $(this).css("display", "none");
                        $(this).parent().removeClass(opt.subMenuParentToggle);
                    }
                });
            }

            // Class Set Up for every submenu
            menu.find("." + opt.subMenuParent).each(function () {
                var submenu = $(this).find("ul");
                submenu.addClass(opt.subMenuClass);
                submenu.css("display", "none");
                $(this).addClass(opt.subMenuParent);
                $(this).addClass(opt.thSubMenuParent); // Add th-item-has-children class
                $(this).children("a").append(opt.appendElement);
            });

            // Toggle Submenu
            function toggleDropDown($element) {
                var submenu = $element.children("ul");
                if (submenu.length > 0) {
                    $element.toggleClass(opt.subMenuParentToggle);
                    submenu.slideToggle(opt.toggleSpeed);
                    submenu.toggleClass(opt.subMenuToggleClass);
                }
            }

            // Submenu toggle Button
            var itemHasChildren = "." + opt.thSubMenuParent + " > a";
            $(itemHasChildren).each(function () {
                $(this).on("click", function (e) {
                    e.preventDefault();
                    toggleDropDown($(this).parent());
                });
            });

            // Menu Show & Hide On Toggle Btn click
            $(opt.menuToggleBtn).each(function () {
                $(this).on("click", function () {
                    menuToggle();
                });
            });

            // Hide Menu On outside click
            menu.on("click", function (e) {
                e.stopPropagation();
                menuToggle();
            });

            // Stop Hide full menu on menu click
            menu.find("div").on("click", function (e) {
                e.stopPropagation();
            });
        });
    };

    $(".th-menu-wrapper").thmobilemenu();

    /*---------- 03.1. Mobile Menu ----------*/
    $.fn.thmobilemenu = function (options) {
        var opt = $.extend({
            menuToggleBtn: ".th-menu-toggle2",
            bodyToggleClass: "th-body-visible",
            subMenuClass: "th-submenu",
            subMenuParent: "menu-item-has-children",
            thSubMenuParent: "th-item-has-children",
            subMenuParentToggle: "th-active",
            meanExpandClass: "th-mean-expand",
            appendElement: '<span class="th-mean-expand"></span>',
            subMenuToggleClass: "th-open",
            toggleSpeed: 400,
        }, options);
    
        return this.each(function () {
            var menu = $(this); // Select menu
    
            // Menu Show & Hide
            function menuToggle() {
                menu.toggleClass(opt.bodyToggleClass);
    
                // Collapse submenu on menu hide or show
                var subMenu = "." + opt.subMenuClass;
                $(subMenu).each(function () {
                    if ($(this).hasClass(opt.subMenuToggleClass)) {
                        $(this).removeClass(opt.subMenuToggleClass);
                        $(this).css("display", "none");
                        $(this).parent().removeClass(opt.subMenuParentToggle);
                    }
                });
            }
    
            // Class Set Up for every submenu
            menu.find("." + opt.subMenuParent).each(function () {
                var submenu = $(this).find("ul");
                submenu.addClass(opt.subMenuClass);
                submenu.css("display", "none");
                $(this).addClass(opt.subMenuParent);
                $(this).addClass(opt.thSubMenuParent); // Add th-item-has-children class
                $(this).children("a").append(opt.appendElement);
            });
    
            // Toggle Submenu
            function toggleDropDown($element) {
                var submenu = $element.children("ul");
                if (submenu.length > 0) {
                    $element.toggleClass(opt.subMenuParentToggle);
                    submenu.slideToggle(opt.toggleSpeed);
                    submenu.toggleClass(opt.subMenuToggleClass);
                }
            }
    
            // Submenu toggle Button
            var itemHasChildren = "." + opt.thSubMenuParent + " > a";
            $(itemHasChildren).each(function () {
                $(this).on("click", function (e) {
                    e.preventDefault();
                    toggleDropDown($(this).parent());
                });
            });
    
            // Menu Show & Hide On Toggle Btn click
            $(opt.menuToggleBtn).each(function () {
                $(this).on("click", function () {
                    menuToggle();
                });
            });
    
            // Hide Menu On outside click (for side menu)
            menu.on("click", function (e) {
                e.stopPropagation();
                menuToggle();
            });
    
            // Stop Hide full menu on menu click
            menu.find("div").on("click", function (e) {
                e.stopPropagation();
            });
    
            // Side menu close when a link is clicked 
            function onePageNav2(element2) {
                $('.th-mobile-menu a').on('click', function () {
                    $('.th-menu-wrapper2').fadeOut();
                    $('.th-menu-wrapper2').removeClass('th-body-visible');
                });

                // Show menu when toggle is clicked
                $('.th-menu-toggle2').on('click', function () {
                    $('.th-menu-wrapper2').fadeIn();
                });
            };
            onePageNav2('.th-mobile-menu a'); 
        });
    };
    
    $(".th-menu-wrapper2").thmobilemenu();
    

    /*----------- 04.1.  One Page Nav ----------*/
    function onePageNav(element) {
        if ($(element).length > 0) {
            $(element).each(function () {
                var link = $(this).find('a');
                $(this).find(link).each(function () {
                    $(this).on('click', function (event) {
                        var target = $(this.getAttribute('href'));
                        if (target.length) {
                            event.preventDefault();
                            $('html, body').stop().animate({
                                scrollTop: target.offset().top - 10
                            }, 1000);
                        }
                    });
                });
            });
        }
    }

    onePageNav('.onepage-nav');
    onePageNav('.scroll-down');

    /*---------- 04. Sticky fix ----------*/
    $(window).scroll(function () {
        var topPos = $(this).scrollTop();
        if (topPos > 500) {
            $('.sticky-wrapper').addClass('sticky');
            $('.category-menu').addClass('close-category');
        } else {
            $('.sticky-wrapper').removeClass('sticky')
            $('.category-menu').removeClass('close-category');
        }
    })

    /*---------- 05. Scroll To Top ----------*/
    if ($('.scroll-top').length > 0) {

        var scrollTopbtn = document.querySelector('.scroll-top');
        var progressPath = document.querySelector('.scroll-top path');
        var pathLength = progressPath.getTotalLength();
        progressPath.style.transition = progressPath.style.WebkitTransition = 'none';
        progressPath.style.strokeDasharray = pathLength + ' ' + pathLength;
        progressPath.style.strokeDashoffset = pathLength;
        progressPath.getBoundingClientRect();
        progressPath.style.transition = progressPath.style.WebkitTransition = 'stroke-dashoffset 10ms linear';
        var updateProgress = function () {
            var scroll = $(window).scrollTop();
            var height = $(document).height() - $(window).height();
            var progress = pathLength - (scroll * pathLength / height);
            progressPath.style.strokeDashoffset = progress;
        }
        updateProgress();
        $(window).scroll(updateProgress);
        var offset = 50;
        var duration = 750;
        jQuery(window).on('scroll', function () {
            if (jQuery(this).scrollTop() > offset) {
                jQuery(scrollTopbtn).addClass('show');
            } else {
                jQuery(scrollTopbtn).removeClass('show');
            }
        });
        jQuery(scrollTopbtn).on('click', function (event) {
            event.preventDefault();
            jQuery('html, body').animate({
                scrollTop: 0
            }, duration);
            return false;
        })
    }

    /*---------- 06. Set Background Image Color & Mask ----------*/
    if ($("[data-bg-src]").length > 0) {
        $("[data-bg-src]").each(function () {
            var src = $(this).attr("data-bg-src");
            $(this).css("background-image", "url(" + src + ")");
            $(this).removeAttr("data-bg-src").addClass("background-image");
        });
    }

    if ($('[data-bg-color]').length > 0) {
        $('[data-bg-color]').each(function () {
            var color = $(this).attr('data-bg-color');
            $(this).css('background-color', color);
            $(this).removeAttr('data-bg-color');
        });
    };

    $('[data-border]').each(function () {
        var borderColor = $(this).data('border');
        $(this).css('--th-border-color', borderColor);
    });

    if ($('[data-mask-src]').length > 0) {
        $('[data-mask-src]').each(function () {
            var mask = $(this).attr('data-mask-src');
            $(this).css({
                'mask-image': 'url(' + mask + ')',
                '-webkit-mask-image': 'url(' + mask + ')'
            });
            $(this).addClass('bg-mask');
            $(this).removeAttr('data-mask-src');
        });
    };


    /*----------- 07. Global Slider ----------*/
    $('.th-slider').each(function () {
        var thSlider = $(this);
        var settings = $(this).data('slider-options');

        // Store references to the navigation Slider
        var prevArrow = thSlider.find('.slider-prev');
        var nextArrow = thSlider.find('.slider-next');
        var paginationElN = thSlider.find('.slider-pagination.pagi-number');
        var paginationExternel = thSlider.siblings('.slider-controller').find('.slider-pagination');

        var paginationEl = paginationExternel.length ? paginationExternel.get(0) : thSlider.find('.slider-pagination').get(0);
        var paginationType = settings['paginationType'] || 'bullets';
        var autoplayconditon = settings['autoplay'];

        var sliderDefault = {
            slidesPerView: 1,
            spaceBetween: settings['spaceBetween'] || 24,
            loop: settings['loop'] !== false,
            speed: settings['speed'] || 1000,
            autoplay: autoplayconditon || {
                delay: 6000,
                disableOnInteraction: false
            },
            navigation: {
                nextEl: nextArrow.get(0),
                prevEl: prevArrow.get(0),
            },
            pagination: {
                el: paginationEl,
                type: paginationType,
                clickable: true,
                renderBullet: function (index, className) {
                    var number = index + 1;
                    var formattedNumber = number < 10 ? '0' + number : number;
                    return paginationElN.length ?
                        '<span class="' + className + ' number">' + formattedNumber + '</span>' :
                        '<span class="' + className + '" aria-label="Go to Slide ' + formattedNumber + '"></span>';
                },
                formatFractionCurrent: function (number) {
                    return number < 10 ? '0' + number : number;
                },
                formatFractionTotal: function (number) {
                    return number < 10 ? '0' + number : number;
                }
            },
            on: {
                slideChange: function () {
                    setTimeout(() => {
                        swiper.params.mousewheel.releaseOnEdges = false;
                    }, 500);
                },
                reachEnd: function () {
                    setTimeout(() => {
                        swiper.params.mousewheel.releaseOnEdges = true;
                    }, 750);
                }
            }
        };

        // Initialize the swiper for the standard slider
        var options = JSON.parse(thSlider.attr('data-slider-options'));
        options = $.extend({}, sliderDefault, options);
        var swiper = new Swiper(thSlider.get(0), options);

        if ($('.slider-area').length > 0) {
            $('.slider-area').closest(".container").parent().addClass("arrow-wrap");
        }
    });

    // Initialize parallax slider
    if ($('.parallax-slider').length > 0) {
        var parallaxSlider;
        var parallaxSliderOptions = {
            speed: 1000,
            autoplay: true,
            parallax: true,
            loop: true,
            spaceBetween: 0,
            grabCursor: true,
            centeredSlides: true,
            pagination: {
                el: '.swiper-pagination',
                clickable: true
            },
            on: {
                init: function () {
                    let swiper = this;
                    for (let i = 0; i < swiper.slides.length; i++) {
                        $(swiper.slides[i]).find('.hero-img').attr({
                            'data-swiper-parallax': 0.75 * swiper.width,
                            'data-swiper-parallax-opacity': 0.5
                        });

                        $(swiper.slides[i]).find('.title').attr('data-swiper-parallax', 0.65 * swiper.width);
                        $(swiper.slides[i]).find('.description').attr('data-swiper-parallax', 0.5 * swiper.width);
                    }
                },
                resize: function () {
                    this.update();
                }
            },
            navigation: {
                nextEl: '.parallax-slider .next-ctrl',
                prevEl: '.parallax-slider .prev-ctrl'
            }
        };

        // Initialize parallax slider
        parallaxSlider = new Swiper('.parallax-slider', parallaxSliderOptions);

        $(window).on('resize', function () {
            parallaxSlider.destroy();
            parallaxSlider = new Swiper('.parallax-slider', parallaxSliderOptions);
        });
    }

    // Function to add animation classes
    function animationProperties() {
        $('[data-ani]').each(function () {
            var animationName = $(this).data('ani');
            $(this).addClass(animationName);
        });

        $('[data-ani-delay]').each(function () {
            var delayTime = $(this).data('ani-delay');
            $(this).css('animation-delay', delayTime);
        });
    }
    animationProperties();

    // Add click event handlers for external slider arrows based on data attributes
    $('[data-slider-prev], [data-slider-next]').on('click', function () {
        var sliderSelector = $(this).data('slider-prev') || $(this).data('slider-next');
        var targetSlider = $(sliderSelector);

        if (targetSlider.length) {
            var swiper = targetSlider[0].swiper;

            if (swiper) {
                if ($(this).data('slider-prev')) {
                    swiper.slidePrev();
                } else {
                    swiper.slideNext();
                }
            }
        }
    });


    /* hero-6  start */
    var swiper = new Swiper(".hero5Thumbs", {
        spaceBetween: 3,
        slidesPerView: 1,
        freeMode: true,
        watchSlidesProgress: true,
    });
    var swiper = new Swiper('.hero-slider-5', {
        thumbs: {
            swiper: swiper,
        },
        loop: true,
        effect: "fade",
        autoplay: {
            delay: 6000,
            disableOnInteraction: false
        },
        pagination: {
            el: '.swiper-pagination',
            type: 'fraction',
            formatFractionCurrent: function (number) {
                return '0' + number;
            }
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev'
        }
    });

    /* hero-6  end */


    /*-------------- 08. Slider Tab -------------*/
    $.fn.activateSliderThumbs = function (options) {
        var opt = $.extend({
                sliderTab: false,
                tabButton: ".tab-btn",
            },
            options
        );

        return this.each(function () {
            var $container = $(this);
            var $thumbs = $container.find(opt.tabButton);
            var $line = $('<span class="indicator"></span>').appendTo($container);

            var sliderSelector = $container.data("slider-tab");
            var $slider = $(sliderSelector);

            var swiper = $slider[0].swiper;

            $thumbs.on("click", function (e) {
                e.preventDefault();
                var clickedThumb = $(this);

                clickedThumb.addClass("active").siblings().removeClass("active");
                linePos(clickedThumb, $container);

                clickedThumb.prevAll(opt.tabButton).addClass('list-active');
                clickedThumb.nextAll(opt.tabButton).removeClass('list-active');

                if (opt.sliderTab) {
                    var slideIndex = clickedThumb.index();
                    swiper.slideTo(slideIndex);
                }
            });

            if (opt.sliderTab) {
                swiper.on("slideChange", function () {
                    var activeIndex = swiper.realIndex;
                    var $activeThumb = $thumbs.eq(activeIndex);

                    $activeThumb.addClass("active").siblings().removeClass("active");
                    linePos($activeThumb, $container);

                    $activeThumb.prevAll(opt.tabButton).addClass('list-active');
                    $activeThumb.nextAll(opt.tabButton).removeClass('list-active');
                });

                var initialSlideIndex = swiper.activeIndex;
                var $initialThumb = $thumbs.eq(initialSlideIndex);
                $initialThumb.addClass("active").siblings().removeClass("active");
                linePos($initialThumb, $container);

                $initialThumb.prevAll(opt.tabButton).addClass('list-active');
                $initialThumb.nextAll(opt.tabButton).removeClass('list-active');
            }

            function linePos($activeThumb) {
                var thumbOffset = $activeThumb.position();

                var marginTop = parseInt($activeThumb.css('margin-top')) || 0;
                var marginLeft = parseInt($activeThumb.css('margin-left')) || 0;

                $line.css("--height-set", $activeThumb.outerHeight() + "px");
                $line.css("--width-set", $activeThumb.outerWidth() + "px");
                $line.css("--pos-y", thumbOffset.top + marginTop + "px");
                $line.css("--pos-x", thumbOffset.left + marginLeft + "px");
            }
        });
    };

    if ($(".testi-box-tab").length) {
        $(".testi-box-tab").activateSliderThumbs({
            sliderTab: true,
            tabButton: ".tab-btn",
        });
    }


    // Custom Search Dropdown Script Start

    var showSuggestions = function () {
        $(".top-search form.header-search .box-search").each(function () {
            var $input = $(this).find("input");
            var $overlay = $(this).closest('.boxed').children('.overlay');
            var $suggestions = $(this).children('.search-suggestions');

            $input.on('focus', function () {
                $overlay.css({
                    opacity: '1',
                    display: 'block'
                });
                $suggestions.css({
                    opacity: '1',
                    visibility: 'visible',
                    top: '50px'
                });
            });

            $input.on('blur', function () {
                // Do nothing on blur, we will handle click outside
            });
        });

        // Click event on the body to hide suggestions
        $(document).on('click', function (e) {
            var $target = $(e.target);
            // Check if the click is outside the input and suggestions
            if (!$target.closest('.box-search').length) {
                $(".top-search .box-search .overlay").css({
                    opacity: '0',
                    display: 'none'
                });
                $(".top-search .box-search .search-suggestions").css({
                    opacity: '0',
                    visibility: 'hidden',
                    top: '100px'
                });
            }
        });
    };

    $(function () {
        showSuggestions();
    });

    /*----------- 09. Ajax Contact Form ----------*/
    var form = ".ajax-contact";
    var invalidCls = "is-invalid";
    var $email = '[name="email"]';
    var $validation =
        '[name="name"],[name="email"],[name="subject"],[name="number"],[name="message"]'; // Must be use (,) without any space
    var formMessages = $(".form-messages");

    function sendContact() {
        var formData = $(form).serialize();
        var valid;
        valid = validateContact();
        if (valid) {
            jQuery
                .ajax({
                    url: $(form).attr("action"),
                    data: formData,
                    type: "POST",
                })
                .done(function (response) {
                    // Make sure that the formMessages div has the 'success' class.
                    formMessages.removeClass("error");
                    formMessages.addClass("success");
                    // Set the message text.
                    formMessages.text(response);
                    // Clear the form.
                    $(
                        form +
                        ' input:not([type="submit"]),' +
                        form +
                        " textarea"
                    ).val("");
                })
                .fail(function (data) {
                    // Make sure that the formMessages div has the 'error' class.
                    formMessages.removeClass("success");
                    formMessages.addClass("error");
                    // Set the message text.
                    if (data.responseText !== "") {
                        formMessages.html(data.responseText);
                    } else {
                        formMessages.html(
                            "Oops! An error occured and your message could not be sent."
                        );
                    }
                });
        }
    }

    function validateContact() {
        var valid = true;
        var formInput;

        function unvalid($validation) {
            $validation = $validation.split(",");
            for (var i = 0; i < $validation.length; i++) {
                formInput = form + " " + $validation[i];
                if (!$(formInput).val()) {
                    $(formInput).addClass(invalidCls);
                    valid = false;
                } else {
                    $(formInput).removeClass(invalidCls);
                    valid = true;
                }
            }
        }
        unvalid($validation);

        if (
            !$($email).val() ||
            !$($email)
            .val()
            .match(/^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/)
        ) {
            $($email).addClass(invalidCls);
            valid = false;
        } else {
            $($email).removeClass(invalidCls);
            valid = true;
        }
        return valid;
    }

    $(form).on("submit", function (element) {
        element.preventDefault();
        sendContact();
    });

    /*---------- 10. Popup Sidemenu ----------*/
    function popupSideMenu($sideMenu, $sideMunuOpen, $sideMenuCls, $toggleCls) {
        // Sidebar Popup
        $($sideMunuOpen).on('click', function (e) {
            e.preventDefault();
            $($sideMenu).addClass($toggleCls);
        });
        // $($sideMenu).on('click', function (e) {
        //     e.stopPropagation();
        //     $($sideMenu).removeClass($toggleCls)
        // });
        var sideMenuChild = $sideMenu + ' > div';
        $(sideMenuChild).on('click', function (e) {
            e.stopPropagation();
            $($sideMenu).addClass($toggleCls)
        });
        $($sideMenuCls).on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $($sideMenu).removeClass($toggleCls);
        });
    };
    popupSideMenu(".popup-search-box", ".searchBoxToggler", ".searchClose", "show");
    popupSideMenu('.sidemenu-cart', '.sideMenuToggler', '.sideMenuCls', 'show');
    popupSideMenu('.sidemenu-info', '.sideMenuInfo', '.sideMenuCls', 'show');

    /*----------- 11. Magnific Popup ----------*/
    /* magnificPopup img view */
    $(".popup-image").magnificPopup({
        type: "image",
        mainClass: 'mfp-zoom-in',
        removalDelay: 260,
        gallery: {
            enabled: true,
        },
    });

    /* magnificPopup video view */
    $(".popup-video").magnificPopup({
        type: "iframe",
    });

    /* magnificPopup video view */
    $(".popup-content").magnificPopup({
        type: "inline",
        midClick: true,
    });


    /* cursor area start*/
    // /*----------- Gsap Animation ----------*/
    if ($('.cursor-follower').length > 0) {
        var follower = $(".cursor-follower");

        var posX = 0,
            posY = 0;

        var mouseX = 0,
            mouseY = 0;

        TweenMax.to({}, 0.016, {
            repeat: -1,
            onRepeat: function () {
                posX += (mouseX - posX) / 9;
                posY += (mouseY - posY) / 9;

                TweenMax.set(follower, {
                    css: {
                        left: posX - 12,
                        top: posY - 12
                    }
                });
            }
        });

        $(document).on("mousemove", function (e) {
            mouseX = e.clientX;
            mouseY = e.clientY;
        });
        //circle
        $(".slider-area").on("mouseenter", function () {
            follower.addClass("d-none");
        });
        $(".slider-area").on("mouseleave", function () {
            follower.removeClass("d-none");
        });
    }


    const cursor = document.querySelector(".slider-drag-cursor");
    const pos = {
        x: window.innerWidth / 2,
        y: window.innerHeight / 2
    };
    const mouse = {
        x: pos.x,
        y: pos.y
    };
    const speed = 1;

    const xSet = gsap.quickSetter(cursor, "x", "px");
    const ySet = gsap.quickSetter(cursor, "y", "px");

    window.addEventListener("pointermove", e => {
        mouse.x = e.x;
        mouse.y = e.y;
    });

    gsap.set(".slider-drag-cursor", {
        xPercent: -50,
        yPercent: -50
    });
    gsap.ticker.add(() => {
        const dt = 1.0 - Math.pow(1.0 - speed, gsap.ticker.deltaRatio());
        pos.x += (mouse.x - pos.x) * dt;
        pos.y += (mouse.y - pos.y) * dt;
        xSet(pos.x);
        ySet(pos.y);
    });


    $(".slider-drag-wrap").hover(function () {
        $('.slider-drag-cursor').addClass('active');
    }, function () {
        $('.slider-drag-cursor').removeClass('active');
    });

    $(".slider-drag-wrap a").hover(function () {
        $('.slider-drag-cursor').removeClass('active');
    }, function () {
        $('.slider-drag-cursor').addClass('active');
    });

    /* cursor area end */
    /*---------- 12. Section Position ----------*/
    // Interger Converter
    function convertInteger(str) {
        return parseInt(str, 10);
    }

    $.fn.sectionPosition = function (mainAttr, posAttr) {
        $(this).each(function () {
            var section = $(this);

            function setPosition() {
                var sectionHeight = Math.floor(section.height() / 2), // Main Height of section
                    posData = section.attr(mainAttr), // where to position
                    posFor = section.attr(posAttr), // On Which section is for positioning
                    topMark = "top-half", // Pos top
                    bottomMark = "bottom-half", // Pos Bottom
                    parentPT = convertInteger($(posFor).css("padding-top")), // Default Padding of  parent
                    parentPB = convertInteger($(posFor).css("padding-bottom")); // Default Padding of  parent

                if (posData === topMark) {
                    $(posFor).css(
                        "padding-bottom",
                        parentPB + sectionHeight + "px"
                    );
                    section.css("margin-top", "-" + sectionHeight + "px");
                } else if (posData === bottomMark) {
                    $(posFor).css(
                        "padding-top",
                        parentPT + sectionHeight + "px"
                    );
                    section.css("margin-bottom", "-" + sectionHeight + "px");
                }
            }
            setPosition(); // Set Padding On Load
        });
    };

    var postionHandler = "[data-sec-pos]";
    if ($(postionHandler).length) {
        $(postionHandler).imagesLoaded(function () {
            $(postionHandler).sectionPosition("data-sec-pos", "data-pos-for");
        });
    }

    /*----------- 13. Filter ----------*/
    $(".filter-active").imagesLoaded(function () {
        var $filter = ".filter-active",
            $filterItem = ".filter-item",
            $filterMenu = ".filter-menu-active";

        if ($($filter).length > 0) {
            var $grid = $($filter).isotope({
                itemSelector: $filterItem,
                filter: "*",
                masonry: {
                    columnWidth: $filterItem,
                },
            });

            // filter items on button click
            $($filterMenu).on("click", "button", function () {
                var filterValue = $(this).attr("data-filter");
                $grid.isotope({
                    filter: filterValue,
                });
            });

            // Menu Active Class
            $($filterMenu).on("click", "button", function (event) {
                event.preventDefault();
                $(this).addClass("active");
                $(this).siblings(".active").removeClass("active");
            });
        }
        $('.load-more-btn').on('click', function () {
            var $icon = $(this).find('i');
            $icon.addClass('fa-spin-pulse');

            setTimeout(function () {
                $icon.removeClass('fa-spin-pulse');
            }, 1000);

            var $button = $(this);
            setTimeout(function () {
                var $closestLoadMoreActive = $button.prev('.load-more-active');
                if ($closestLoadMoreActive.length === 0) {
                    $closestLoadMoreActive = $button.closest('.container').find('.load-more-active');
                }
                $closestLoadMoreActive.find('.filter-item.d-none, .accordion-card.d-none').removeClass('d-none');

                var $grid = $($filter).isotope({
                    filter: "*",
                });
            }, 700);
        });
    });

    $(".masonary-active, .woocommerce-Reviews .comment-list").imagesLoaded(function () {
        var $filter = ".masonary-active, .woocommerce-Reviews .comment-list",
            $filterItem = ".filter-item, .woocommerce-Reviews .comment-list li";

        if ($($filter).length > 0) {
            $($filter).isotope({
                itemSelector: $filterItem,
                filter: "*",
                masonry: {
                    // use outer width of grid-sizer for columnWidth
                    columnWidth: 1,
                },
            });
        }
        $('[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            $($filter).isotope({
                filter: "*",
            });
        });
    });

    /************lettering js***********/
    function injector(t, splitter, klass, after) {
        var a = t.text().split(splitter),
            inject = '';
        if (a.length) {
            $(a).each(function (i, item) {
                inject += '<span class="' + klass + (i + 1) + '">' + item + '</span>' + after;
            });
            t.empty().append(inject);
        }
    }

    var methods = {
        init: function () {

            return this.each(function () {
                injector($(this), '', 'char', '');
            });

        },

        words: function () {

            return this.each(function () {
                injector($(this), ' ', 'word', ' ');
            });

        },

        lines: function () {

            return this.each(function () {
                var r = "eefec303079ad17405c889e092e105b0";
                // Because it's hard to split a <br/> tag consistently across browsers,
                // (*ahem* IE *ahem*), we replaces all <br/> instances with an md5 hash 
                // (of the word "split").  If you're trying to use this plugin on that 
                // md5 hash string, it will fail because you're being ridiculous.
                injector($(this).children("br").replaceWith(r).end(), r, 'line', '');
            });

        }
    };

    $.fn.lettering = function (method) {
        // Method calling logic
        if (method && methods[method]) {
            return methods[method].apply(this, [].slice.call(arguments, 1));
        } else if (method === 'letters' || !method) {
            return methods.init.apply(this, [].slice.call(arguments, 0)); // always pass an array
        }
        $.error('Method ' + method + ' does not exist on jQuery.lettering');
        return this;
    };

    $(".discount-anime").lettering();


    /*----------- 14. Counter Up ----------*/
    $(".counter-number").counterUp({
        delay: 10,
        time: 1000,
    });

    /*----------- 15. Hover Item ----------*/
    $(".hover-item").hover(function () {
        $(this).addClass("item-active");
        $(this).siblings().removeClass("item-active");
    });

    /*----------- 16. Shape Mockup ----------*/
    $.fn.shapeMockup = function () {
        var $shape = $(this);
        $shape.each(function () {
            var $currentShape = $(this),
                shapeTop = $currentShape.data("top"),
                shapeRight = $currentShape.data("right"),
                shapeBottom = $currentShape.data("bottom"),
                shapeLeft = $currentShape.data("left");
            $currentShape
                .css({
                    top: shapeTop,
                    right: shapeRight,
                    bottom: shapeBottom,
                    left: shapeLeft,
                })
                .removeAttr("data-top")
                .removeAttr("data-right")
                .removeAttr("data-bottom")
                .removeAttr("data-left")
                .parent()
                .addClass("shape-mockup-wrap");
        });
    };

    if ($(".shape-mockup")) {
        $(".shape-mockup").shapeMockup();
    }

    /*----------- 16. Shape Mockup ----------*/
    $.fn.shapeMockup2 = function () {
        var $shape = $(this);
        $shape.each(function () {
            var $currentShape = $(this),
                shapeTop = $currentShape.data("top"),
                shapeRight = $currentShape.data("right"),
                shapeBottom = $currentShape.data("bottom"),
                shapeLeft = $currentShape.data("left");
            $currentShape
                .css({
                    top: shapeTop,
                    right: shapeRight,
                    bottom: shapeBottom,
                    left: shapeLeft,
                })
                .removeAttr("data-top")
                .removeAttr("data-right")
                .removeAttr("data-bottom")
                .removeAttr("data-left")
                .parent()
                .addClass("shape-mockup-wrap2");
        });
    };

    if ($(".shape-mockup2")) {
        $(".shape-mockup2").shapeMockup2();
    }

    /*----------- 17. Progress Bar Animation ----------*/
    $('.progress-bar').waypoint(function () {
        $('.progress-bar').css({
            animation: "animate-positive 1.8s",
            opacity: "1"
        });
    }, {
        offset: '75%'
    });

    /*----------- 18. Countdown ----------*/

    $.fn.countdown = function () {
        $(this).each(function () {
            var $counter = $(this),
                countDownDate = new Date($counter.data("offer-date")).getTime(), // Set the date we're counting down toz
                exprireCls = "expired";

            // Finding Function
            function s$(element) {
                return $counter.find(element);
            }

            // Update the count down every 1 second
            var counter = setInterval(function () {
                // Get today's date and time
                var now = new Date().getTime();

                // Find the distance between now and the count down date
                var distance = countDownDate - now;

                // Time calculations for days, hours, minutes and seconds
                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor(
                    (distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)
                );
                var minutes = Math.floor(
                    (distance % (1000 * 60 * 60)) / (1000 * 60)
                );
                var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                // Check If value is lower than ten, so add zero before number
                days < 10 ? (days = "0" + days) : null;
                hours < 10 ? (hours = "0" + hours) : null;
                minutes < 10 ? (minutes = "0" + minutes) : null;
                seconds < 10 ? (seconds = "0" + seconds) : null;

                // If the count down is over, write some text
                if (distance < 0) {
                    clearInterval(counter);
                    $counter.addClass(exprireCls);
                    $counter.find(".message").css("display", "block");
                } else {
                    // Output the result in elements
                    s$(".day").html(days);
                    s$(".hour").html(hours);
                    s$(".minute").html(minutes);
                    s$(".seconds").html(seconds);
                }
            }, 1000);
        });
    };

    if ($(".counter-list").length) {
        $(".counter-list").countdown();
    }
    /* product start --------------------*/
    $(document).on('mouseover', '.hover-item', function () {
        $(this).addClass('item-active');
        $('.hover-item').removeClass('item-active');
        $(this).addClass('item-active');
    });
    /* product end --------------------*/
    /*---------- 19. Hero 2 Animation ----------*/
    if ($('.hero-2').length > 0) {
        window.addEventListener('scroll', function () {
            let value = window.scrollY;
            $('.line').css('width', "calc(" + 'var(--size)' + " - " + value * 0.1 + 'px' + ")");
        })
    };

    /*----------- 20. Tilt Active ----------*/
    $('.tilt-active').tilt({
        maxTilt: 15,
        perspective: 1000,
    })

    /*----------- 21. Overlay Direction ----------*/
    if ($('.overlay-direction').length > 0) {
        if (window.innerWidth > 767) {
            const nodes = [].slice.call(document.querySelectorAll(".overlay-direction .filter-item"), 0);
            const directions = {
                0: "top",
                1: "right",
                2: "bottom",
                3: "left"
            };
            const classNames = ["in", "out"].map(p => Object.values(directions).map(d => `${p}-${d}`)).reduce((a, b) => a.concat(b));
            const getDirectionKey = (ev, node) => {
                const {
                    width,
                    height,
                    top,
                    left
                } = node.getBoundingClientRect();
                const l = ev.pageX - (left + window.pageXOffset);
                const t = ev.pageY - (top + window.pageYOffset);
                const x = l - width / 2 * (width > height ? height / width : 1);
                const y = t - height / 2 * (height > width ? width / height : 1);
                return Math.round(Math.atan2(y, x) / 1.57079633 + 5) % 4;
            };
            class Item {
                constructor(element) {
                    this.element = element;
                    this.element.addEventListener("mouseover", ev => this.update(ev, "in"));
                    this.element.addEventListener("mouseout", ev => this.update(ev, "out"));
                }
                update(ev, prefix) {
                    this.element.classList.remove(...classNames);
                    this.element.classList.add(`${prefix}-${directions[getDirectionKey(ev, this.element)]}`);
                }
            }
            nodes.forEach(node => new Item(node));
        }
    }

    /*----------- 22. Indicator ----------*/
    // Indicator
    $.fn.indicator = function () {
        // Loop through each .indicator-active element
        $(this).each(function () {
            var $menu = $(this),
                $linkBtn = $menu.find("a"),
                $btn = $menu.find("button");

            // Append indicator
            $menu.append('<span class="indicator"></span>');
            var $line = $menu.find(".indicator");

            // Check which type button is Available
            var $currentBtn;
            if ($linkBtn.length) {
                $currentBtn = $linkBtn;
            } else if ($btn.length) {
                $currentBtn = $btn;
            }

            // On Click Button Class Remove
            $currentBtn.on("click", function (e) {
                e.preventDefault();
                $(this).addClass("active");
                $(this).siblings(".active").removeClass("active");
                linePos();
            });

            // Indicator Position
            function linePos() {
                var $btnActive = $menu.find(".active"),
                    $height = $btnActive.css("height"),
                    $width = $btnActive.css("width"),
                    $top = $btnActive.position().top + "px",
                    $left = $btnActive.position().left + "px";

                $(window).on('resize', function () {
                    $top = $btnActive.position().top + "px",
                        $left = $btnActive.position().left + "px";
                });

                $line.get(0).style.setProperty("--height-set", $height);
                $line.get(0).style.setProperty("--width-set", $width);
                $line.get(0).style.setProperty("--pos-y", $top);
                $line.get(0).style.setProperty("--pos-x", $left);
            }

            linePos();
            $(window).on('resize', function () {
                linePos();
            });
        });
    };

    if ($(".indicator-active").length) {
        $(".indicator-active").indicator();
    }

    /*  footer animation  */
    $(".th-screen").length && $(window).on("scroll", function () {
            ! function (t, a = 0) {
                var i = $(window).scrollTop(),
                    o = i + $(window).height(),
                    s = $(t).offset().top;
                return s + $(t).height() - parseInt(a) <= o && s >= i
            }
            (".th-screen", 200) ? $(".th-screen").removeClass("th-visible"): $(".th-screen").addClass("th-visible")
        }),

        /*----------- 20. image Slider ----------*/
        $("#compslider").on("input change", (e) => {
            const sliderPos = e.target.value;
            $('.foreground-img').css('width', `${sliderPos}%`)
            $('.slider-button').css('left', `calc(${sliderPos}% - 32px)`)
        });

    /*----------- 00. Color Scheme ----------*/
    $('.color-switch-btns button').each(function () {
        // Get color for button
        const button = $(this);
        const color = button.data('color');
        button.css('--theme-color', color);

        // Change theme color on click
        button.on('click', function () {
            const clickedColor = $(this).data('color');
            $(':root').css('--theme-color', clickedColor);
        });
    });

    // Handle color picker change event
    $('#thcolorpicker').on('input', function () {
        const pickedColor = $(this).val();
        updateThemeColor(pickedColor);
    });

    function updateThemeColor(color) {
        $(':root').css('--theme-color', color);
    }

    $(document).on('click', '.switchIcon', function () {
        $('.color-scheme').toggleClass('active');
    });

    /*----------- 21. Price Slider ----------*/
    $(".price_slider").slider({
        range: true,
        min: 0,
        max: 100,
        values: [0, 100],
        slide: function (event, ui) {
            $(".from").text("$" + ui.values[0]);
            $(".to").text("$" + ui.values[1]);
        }
    });
    $(".from").text("$" + $(".price_slider").slider("values", 0));
    $(".to").text("$" + $(".price_slider").slider("values", 1));


    /*----------- 00. Woocommerce Toggle ----------*/
    // Ship To Different Address
    $("#ship-to-different-address-checkbox").on("change", function () {
        if ($(this).is(":checked")) {
            $("#ship-to-different-address")
                .next(".shipping_address")
                .slideDown();
        } else {
            $("#ship-to-different-address").next(".shipping_address").slideUp();
        }
    });

    // Login Toggle
    $(".woocommerce-form-login-toggle a").on("click", function (e) {
        e.preventDefault();
        $(".woocommerce-form-login").slideToggle();
    });

    // Coupon Toggle
    $(".woocommerce-form-coupon-toggle a").on("click", function (e) {
        e.preventDefault();
        $(".woocommerce-form-coupon").slideToggle();
    });

    // Woocommerce Shipping Method
    $(".shipping-calculator-button").on("click", function (e) {
        e.preventDefault();
        $(this).next(".shipping-calculator-form").slideToggle();
    });

    // Woocommerce Payment Toggle
    $('.wc_payment_methods input[type="radio"]:checked')
        .siblings(".payment_box")
        .show();
    $('.wc_payment_methods input[type="radio"]').each(function () {
        $(this).on("change", function () {
            $(".payment_box").slideUp();
            $(this).siblings(".payment_box").slideDown();
        });
    });

    // Woocommerce Rating Toggle
    $(".rating-select .stars a").each(function () {
        $(this).on("click", function (e) {
            e.preventDefault();
            $(this).siblings().removeClass("active");
            $(this).parent().parent().addClass("selected");
            $(this).addClass("active");
        });
    });

    // Quantity Plus Minus ---------------------------

    $(".quantity-plus").each(function () {
        $(this).on("click", function (e) {
            e.preventDefault();
            var $qty = $(this).siblings(".qty-input");
            var currentVal = parseInt($qty.val(), 10);
            if (!isNaN(currentVal)) {
                $qty.val(currentVal + 1);
            }
        });
    });

    $(".quantity-minus").each(function () {
        $(this).on("click", function (e) {
            e.preventDefault();
            var $qty = $(this).siblings(".qty-input");
            var currentVal = parseInt($qty.val(), 10);
            if (!isNaN(currentVal) && currentVal > 1) {
                $qty.val(currentVal - 1);
            }
        });
    });




    // /*----------- 00. Right Click Disable ----------*/
    //   window.addEventListener('contextmenu', function (e) {
    //     // do something here...
    //     e.preventDefault();  
    //   }, false);

    // /*----------- 00. Inspect Element Disable ----------*/
    //   document.onkeydown = function (e) {  
    //     if (event.keyCode == 123) {
    //       return false;
    //     }
    //     if (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) {
    //       return false;
    //     }
    //     if (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) {
    //       return false;
    //     }
    //     if (e.ctrlKey && e.shiftKey && e.keyCode == 'J'.charCodeAt(0)) {
    //       return false;
    //     }
    //     if (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0)) {
    //       return false;
    //     }
    //   } 

})(jQuery);