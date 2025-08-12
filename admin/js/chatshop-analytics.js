/**
 * ChatShop Analytics Admin JavaScript
 *
 * File: admin/js/analytics.js
 * 
 * Handles the analytics dashboard interactions, chart rendering,
 * and AJAX requests for analytics data.
 *
 * @package ChatShop
 * @subpackage Admin\JavaScript
 * @since 1.0.0
 */

(function($) {
    'use strict';

    let revenueChart = null;
    let funnelChart = null;

    /**
     * Analytics dashboard object
     */
    const ChatShopAnalytics = {
        
        /**
         * Initialize analytics dashboard
         */
        init: function() {
            this.bindEvents();
            this.loadDashboardData();
            
            if (!chatshopAnalytics.isPremium) {
                this.showPremiumOverlay();
            }
        },

        /**
         * Bind event listeners
         */
        bindEvents: function() {
            $(document).ready(function() {
                // Date range change
                $('#analytics-date-range').on('change', function() {
                    if (chatshopAnalytics.isPremium) {
                        ChatShopAnalytics.loadDashboardData();
                    }
                });

                // Refresh button
                $('#refresh-analytics').on('click', function() {
                    if (chatshopAnalytics.isPremium) {
                        ChatShopAnalytics.loadDashboardData();
                    }
                });

                // Export report button
                $('#export-report').on('click', function() {
                    if (chatshopAnalytics.isPremium) {
                        ChatShopAnalytics.exportReport();
                    }
                });

                // Generate custom report
                $('#generate-custom-report').on('click', function() {
                    if (chatshopAnalytics.isPremium) {
                        ChatShopAnalytics.generateCustomReport();
                    }
                });
            });
        },

        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            if (!chatshopAnalytics.isPremium) {
                return;
            }

            this.showLoading();

            const dateRange = $('#analytics-date-range').val();

            // Load summary metrics
            this.loadSummaryMetrics(dateRange);
            
            // Load charts
            this.loadRevenueChart(dateRange);
            this.loadConversionFunnel(dateRange);
            
            // Load tables
            this.loadRevenueTables(dateRange);
            this.loadCampaignTable(dateRange);
        },

        /**
         * Load summary metrics
         */
        loadSummaryMetrics: function(dateRange) {
            $.ajax({
                url: chatshopAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_get_analytics_dashboard',
                    nonce: chatshopAnalytics.nonce,
                    date_range: dateRange
                },
                success: function(response) {
                    if (response.success && response.data.totals) {
                        const totals = response.data.totals;
                        
                        $('#total-conversions').text(ChatShopAnalytics.formatNumber(totals.conversions || 0));
                        $('#total-revenue').text(ChatShopAnalytics.formatCurrency(totals.revenue || 0));
                        $('#conversion-rate').text(ChatShopAnalytics.formatPercentage(totals.conversion_rate || 0));
                        $('#avg-order-value').text(ChatShopAnalytics.formatCurrency(totals.avg_order_value || 0));
                    }
                },
                error: function() {
                    ChatShopAnalytics.showError(chatshopAnalytics.strings.error);
                }
            });
        },

        /**
         * Load revenue chart
         */
        loadRevenueChart: function(dateRange) {
            $.ajax({
                url: chatshopAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_get_revenue_chart',
                    nonce: chatshopAnalytics.nonce,
                    date_range: dateRange,
                    group_by: 'day'
                },
                success: function(response) {
                    if (response.success && response.data) {
                        ChatShopAnalytics.renderRevenueChart(response.data);
                    }
                },
                error: function() {
                    console.warn('Failed to load revenue chart data');
                }
            });
        },

        /**
         * Load conversion funnel
         */
        loadConversionFunnel: function(dateRange) {
            $.ajax({
                url: chatshopAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_get_conversion_funnel',
                    nonce: chatshopAnalytics.nonce,
                    date_range: dateRange
                },
                success: function(response) {
                    if (response.success && response.data.funnel_steps) {
                        ChatShopAnalytics.renderFunnelChart(response.data.funnel_steps);
                    }
                },
                error: function() {
                    console.warn('Failed to load conversion funnel data');
                }
            });
        },

        /**
         * Load revenue tables
         */
        loadRevenueTables: function(dateRange) {
            // Simulate revenue by source data for demo
            const revenueSourceData = [
                { source: 'WhatsApp', conversions: 145, revenue: 12450, avg_value: 85.86 },
                { source: 'Direct', conversions: 89, revenue: 7890, avg_value: 88.65 },
                { source: 'Campaign', conversions: 67, revenue: 5670, avg_value: 84.63 }
            ];

            this.populateRevenueTable(revenueSourceData);
        },

        /**
         * Load campaign table
         */
        loadCampaignTable: function(dateRange) {
            // Simulate campaign data for demo
            const campaignData = [
                { 
                    campaign: 'Summer Sale 2024', 
                    messages: 1250, 
                    clicks: 325, 
                    conversions: 45, 
                    revenue: 3850, 
                    roi: 85.56 
                },
                { 
                    campaign: 'Product Launch', 
                    messages: 890, 
                    clicks: 234, 
                    conversions: 32, 
                    revenue: 2740, 
                    roi: 85.63 
                }
            ];

            this.populateCampaignTable(campaignData);
        },

        /**
         * Render revenue chart
         */
        renderRevenueChart: function(data) {
            const ctx = document.getElementById('revenue-chart');
            if (!ctx) return;

            // Destroy existing chart
            if (revenueChart) {
                revenueChart.destroy();
            }

            const labels = data.map(item => item.period);
            const revenues = data.map(item => parseFloat(item.revenue) || 0);
            const conversions = data.map(item => parseInt(item.conversions) || 0);

            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue',
                        data: revenues,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    }, {
                        label: 'Conversions',
                        data: conversions,
                        borderColor: '#00a32a',
                        backgroundColor: 'rgba(0, 163, 42, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Revenue'
                            },
                            ticks: {
                                callback: function(value) {
                                    return ChatShopAnalytics.formatCurrency(value);
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Conversions'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.datasetIndex === 0) {
                                        return 'Revenue: ' + ChatShopAnalytics.formatCurrency(context.parsed.y);
                                    } else {
                                        return 'Conversions: ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Render funnel chart
         */
        renderFunnelChart: function(funnelSteps) {
            const ctx = document.getElementById('funnel-chart');
            if (!ctx) return;

            // Destroy existing chart
            if (funnelChart) {
                funnelChart.destroy();
            }

            const labels = funnelSteps.map(step => step.label);
            const values = funnelSteps.map(step => step.count);
            const percentages = funnelSteps.map(step => step.percentage);

            funnelChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Count',
                        data: values,
                        backgroundColor: [
                            '#2271b1',
                            '#00a32a',
                            '#dba617',
                            '#d63638',
                            '#8c8f94'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const index = context.dataIndex;
                                    return `${context.parsed.x} (${percentages[index]}%)`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        }
                    }
                }
            });
        },

        /**
         * Populate revenue table
         */
        populateRevenueTable: function(data) {
            const tbody = $('#revenue-source-tbody');
            tbody.empty();

            if (data.length === 0) {
                tbody.append('<tr><td colspan="4" class="no-data">' + chatshopAnalytics.strings.noData + '</td></tr>');
                return;
            }

            data.forEach(function(row) {
                const tr = $('<tr>');
                tr.append('<td>' + row.source + '</td>');
                tr.append('<td>' + ChatShopAnalytics.formatNumber(row.conversions) + '</td>');
                tr.append('<td>' + ChatShopAnalytics.formatCurrency(row.revenue) + '</td>');
                tr.append('<td>' + ChatShopAnalytics.formatCurrency(row.avg_value) + '</td>');
                tbody.append(tr);
            });
        },

        /**
         * Populate campaign table
         */
        populateCampaignTable: function(data) {
            const tbody = $('#campaigns-tbody');
            tbody.empty();

            if (data.length === 0) {
                tbody.append('<tr><td colspan="6" class="no-data">' + chatshopAnalytics.strings.noData + '</td></tr>');
                return;
            }

            data.forEach(function(row) {
                const tr = $('<tr>');
                tr.append('<td>' + row.campaign + '</td>');
                tr.append('<td>' + ChatShopAnalytics.formatNumber(row.messages) + '</td>');
                tr.append('<td>' + ChatShopAnalytics.formatNumber(row.clicks) + '</td>');
                tr.append('<td>' + ChatShopAnalytics.formatNumber(row.conversions) + '</td>');
                tr.append('<td>' + ChatShopAnalytics.formatCurrency(row.revenue) + '</td>');
                tr.append('<td>' + ChatShopAnalytics.formatCurrency(row.roi) + '</td>');
                tbody.append(tr);
            });
        },

        /**
         * Export report
         */
        exportReport: function() {
            this.showLoading();

            const dateRange = $('#analytics-date-range').val();

            $.ajax({
                url: chatshopAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_export_analytics_report',
                    nonce: chatshopAnalytics.nonce,
                    report_type: 'performance',
                    date_range: dateRange,
                    format: 'csv'
                },
                success: function(response) {
                    ChatShopAnalytics.hideLoading();
                    
                    if (response.success) {
                        if (response.data.file_url) {
                            // Create download link
                            const link = document.createElement('a');
                            link.href = response.data.file_url;
                            link.download = response.data.filename;
                            link.click();
                        }
                        ChatShopAnalytics.showSuccess(chatshopAnalytics.strings.exportSuccess);
                    } else {
                        ChatShopAnalytics.showError(response.data.message || chatshopAnalytics.strings.exportError);
                    }
                },
                error: function() {
                    ChatShopAnalytics.hideLoading();
                    ChatShopAnalytics.showError(chatshopAnalytics.strings.exportError);
                }
            });
        },

        /**
         * Generate custom report
         */
        generateCustomReport: function() {
            const reportType = $('#report-type').val();
            const format = $('#report-format').val();
            const dateRange = $('#analytics-date-range').val();

            this.showLoading();

            $.ajax({
                url: chatshopAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'chatshop_generate_custom_report',
                    nonce: chatshopAnalytics.nonce,
                    title: 'Custom Analytics Report',
                    date_range: dateRange,
                    metrics: ['conversion_funnel', 'revenue_trends', 'attribution'],
                    format: format
                },
                success: function(response) {
                    ChatShopAnalytics.hideLoading();
                    
                    if (response.success) {
                        if (format === 'csv' && response.data.file_url) {
                            const link = document.createElement('a');
                            link.href = response.data.file_url;
                            link.download = response.data.filename;
                            link.click();
                        } else if (format === 'html') {
                            ChatShopAnalytics.displayHTMLReport(response.data);
                        }
                        ChatShopAnalytics.showSuccess('Custom report generated successfully!');
                    } else {
                        ChatShopAnalytics.showError(response.data.message || 'Failed to generate report');
                    }
                },
                error: function() {
                    ChatShopAnalytics.hideLoading();
                    ChatShopAnalytics.showError('Failed to generate custom report');
                }
            });
        },

        /**
         * Show premium overlay for non-premium users
         */
        showPremiumOverlay: function() {
            $('.summary-card h3').text('-');
            $('#revenue-source-tbody').html('<tr><td colspan="4" class="no-data">' + chatshopAnalytics.strings.premiumRequired + '</td></tr>');
            $('#campaigns-tbody').html('<tr><td colspan="6" class="no-data">' + chatshopAnalytics.strings.premiumRequired + '</td></tr>');
        },

        /**
         * Display HTML report in modal
         */
        displayHTMLReport: function(reportData) {
            // Create modal for HTML report display
            const modal = $('<div class="chatshop-report-modal">').html(reportData);
            $('body').append(modal);
            
            // Add close functionality
            modal.on('click', function(e) {
                if (e.target === this) {
                    $(this).remove();
                }
            });
        },

        /**
         * Utility functions
         */
        formatNumber: function(num) {
            return new Intl.NumberFormat().format(num || 0);
        },

        formatCurrency: function(amount) {
            return new Intl.NumberFormat('en-NG', {
                style: 'currency',
                currency: 'NGN',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount || 0);
        },

        formatPercentage: function(percent) {
            return (percent || 0).toFixed(2) + '%';
        },

        showLoading: function() {
            $('#analytics-loading').show();
        },

        hideLoading: function() {
            $('#analytics-loading').hide();
        },

        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        showError: function(message) {
            this.showNotice(message, 'error');
        },

        showNotice: function(message, type) {
            const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.chatshop-analytics-page .wrap h1').after(notice);
            
            setTimeout(function() {
                notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        ChatShopAnalytics.init();
    });

    // Make globally available
    window.ChatShopAnalytics = ChatShopAnalytics;

})(jQuery);