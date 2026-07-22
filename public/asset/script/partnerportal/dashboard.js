/**
 * Dashboard specific JavaScript
 * Handles Prepaid, Postpaid, and No-Subscription states
 */
(function($) {
    'use strict';

    /**
     * Dashboard Controller
     */
    const DashboardController = {
        config: null,
        mode: null,
        apiUrl: null,
        dashboardData: null,
        isLoading: false,

        /**
         * Initialize the dashboard
         */
        init: function() {
            this.loadConfigFromDataAttributes();

            if (!this.apiUrl) {
                console.error('API URL not configured');
                this.showErrorState('Dashboard API endpoint not configured.');
                return;
            }

            this.bindEvents();

            if (this.dashboardData) {
                console.log('Using initial dashboard data from server');
                this.handleApiSuccess(this.dashboardData);
            } else {
                this.fetchDashboardData();
            }
        },

        loadConfigFromDataAttributes: function() {
            const $body = $('body');
            const configData = $body.data('dashboard-config');

            if (configData) {
                this.apiUrl = configData.apiUrl || null;

                if (configData.hasSubscriptionPlan === 1 || configData.hasSubscriptionPlan === '1') {
                    const type = (configData.paymentType || '').toLowerCase();
                    this.mode = (type === 'postpaid') ? 'postpaid' : 'prepaid';
                } else {
                    this.mode = 'no-subscription';
                }

                // 🔥 STORE FULL CONFIG (IMPORTANT)
                this.dashboardData = configData;
            } else {
                this.apiUrl = '/v2/partner/dashboard';
                this.dashboardData = {};
            }
        },

        fetchDashboardData: function() {
            if (this.isLoading) return;

            this.isLoading = true;
            this.showLoadingState();

            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

            $.ajax({
                url: this.apiUrl,
                method: 'GET',
                dataType: 'json',
                headers,
                xhrFields: { withCredentials: true },
                success: (response) => {
                    this.isLoading = false;
                    this.handleApiSuccess(response);
                },
                error: (xhr, status, error) => {
                    this.isLoading = false;
                    this.handleApiError(xhr, status, error);
                }
            });
        },

        handleApiSuccess: function(response) {
            // MERGE API RESPONSE SAFELY
            this.dashboardData = { ...this.dashboardData, ...response };

            if (response.has_subscription_plan === 1 || response.has_subscription_plan === '1') {
                const type = (response.payment_type || '').toLowerCase();
                this.mode = (type === 'postpaid') ? 'postpaid' : 'prepaid';
            } else {
                this.mode = 'no-subscription';
            }

            window.dashboardMode = this.mode;
            window.dashboardData = this.dashboardData;

            this.updateDashboardUI();
            this.hideZeroValueStatCards();
        },

        updateDashboardUI: function() {
            $('.stats-grid, .usage-grid, .no-subscription-state').hide();

            if (this.mode === 'prepaid') {
                this.updatePrepaidUI();
            } else if (this.mode === 'postpaid') {
                this.showPostpaidUI();
            } else {
                this.showNoSubscriptionUI();
            }
        },

        updatePrepaidUI: function() {
            if (this.mode !== 'prepaid') return;

            $('.stats-grid').show();
            this.hideZeroValueStatCards();
        },

        showPostpaidUI: function() {
            $('.usage-grid').show();
            // Update Current Month usage card with API data
            this.updatePostpaidUsageCard();
            // Animate stat cards for postpaid
            this.animateStatCards();
            // Hide zero-value cards
            this.hideZeroValueStatCards();
        },

        updatePostpaidUsageCard: function() {
            const data = this.dashboardData || {};
            
            // Update Number Of Riders Used
            const totalUsedRider = Number(data.total_used_rider ?? data.totalUsedRider ?? 0);
            $('[data-update-key="totalUsedRider"]').text(totalUsedRider);
            
            // Update Price Per Rider
            const inboundPrice = Number(data.inbound_price_per_rider ?? data.inboundPricePerRider ?? 0);
            const outboundPrice = Number(data.outbound_price_per_rider ?? data.outboundPricePerRider ?? 0);
            
            let priceText = `AED ${inboundPrice}`;
            if (outboundPrice > 0 && outboundPrice !== inboundPrice) {
                priceText += ` - ${outboundPrice}`;
            }
            $('[data-update-key="pricePerRider"]').text(priceText);
            
            // Update Total Amount
            const totalAmount = Number(data.total_amount ?? data.totalAmount ?? 0);
            const formattedAmount = totalAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            $('[data-update-key="totalAmount"]').text(`AED ${formattedAmount}`);
        },

        showNoSubscriptionUI: function() {
            $('.no-subscription-state').show();
        },

        initPrepaid: function() {
            $('.dashboard-loading').remove();
            $('.usage-grid').hide();

            this.animateStatCards();
            this.setupPrepaidTooltips();

            setTimeout(() => {
                this.hideZeroValueStatCards();
            }, 300);
        },

        /* =====================================================
           ✅ FIXED: RELY ON CONFIG, NOT DOM TEXT
        ===================================================== */
        hideZeroValueStatCards: function () {
            const data = this.dashboardData || {};

            $('.stat-card').each(function () {
                const key = $(this).data('stat-key');
                const value = Number(data[key] ?? 0);

                if (value <= 0) {
                    $(this).css('display', 'none');
                } else {
                    $(this).css('display', 'block');
                }
            });
        },

        bindEvents: function() {
            $(document).on('click', '.btn-view-plans', e => {
                e.preventDefault();
                this.handleViewPlansClick();
            });

            $(document).on('click', '.btn-refresh-data', e => {
                e.preventDefault();
                this.refreshDashboardData();
            });

            $(document).on('click', '.btn-retry-dashboard', e => {
                e.preventDefault();
                this.refreshDashboardData();
            });
        },

        handleViewPlansClick: function() {
            if (typeof window.showPlansModal === 'function') {
                window.showPlansModal();
            } else {
                alert('Redirecting to subscription plans...');
            }
        },

        refreshDashboardData: function() {
            $('.dashboard-error').remove();
            this.fetchDashboardData();
        },

        animateStatCards: function() {
            $('.stat-card').each(function(index) {
                // Read value directly from the DOM so it works with any backend keys
                const $valueEl = $(this).find('.stat-value');
                const rawText = ($valueEl.text() || '').toString().replace(/,/g, '').trim();
                const value = Number(rawText || 0);

                if (value <= 0) {
                    $(this).hide();
                    return;
                }

                $(this).css({
                    opacity: 0,
                    transform: 'translateY(20px)'
                }).delay(index * 100).animate({
                    opacity: 1
                }, 300, function() {
                    $(this).css('transform', 'translateY(0)');
                });
            });
        },

        setupPrepaidTooltips: function() {
            if (typeof $().tooltip === 'function') {
                $('.stat-card').tooltip();
            }
        }
    };

    $(document).ready(function() {
        DashboardController.init();
    });

    window.DashboardController = DashboardController;

})(jQuery);
