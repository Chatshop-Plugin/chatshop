/**
 * ChatShop Analytics Dashboard JavaScript
 *
 * File: admin/js/chatshop-analytics.js
 * 
 * Handles analytics dashboard interactions, chart rendering with vanilla JavaScript,
 * and real-time data updates via WordPress AJAX.
 *
 * @package ChatShop
 * @subpackage Admin\Assets
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * ChatShop Analytics Dashboard Class
     */
    class ChatShopAnalytics {
        constructor() {
            this.charts = {};
            this.currentDateRange = '7days';
            this.isLoading = false;
            
            this.init();
        }

        /**
         * Initialize the analytics dashboard
         */
        init() {
            this.bindEvents();
            this.loadAnalytics();
            
            // Auto-refresh every 5 minutes
            setInterval(() => {
                if (!this.isLoading) {
                    this.loadAnalytics(false);
                }
            }, 300000);
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;

            // Date range selector
            $('#analytics-date-range').on('change', function() {
                self.currentDateRange = $(this).val();
                self.loadAnalytics();
            });

            // Refresh button
            $('#refresh-analytics').on('click', function() {
                self.loadAnalytics();
            });

            // Export button
            $('#export-analytics').on('click', function() {
                self.exportAnalytics();
            });

            // Window resize handler for responsive charts
            $(window).on('resize', this.debounce(() => {
                self.resizeCharts();
            }, 250));
        }

        /**
         * Load analytics data
         * @param {boolean} showLoading Whether to show loading indicator
         */
        loadAnalytics(showLoading = true) {
            if (this.isLoading) return;
            
            this.isLoading = true;
            
            if (showLoading) {
                this.showLoading();
            }

            // Load overview data
            this.loadOverviewData()
                .then(() => this.loadConversionStats())
                .then(() => this.loadRevenueAttribution())
                .then(() => this.loadPerformanceMetrics())
                .then(() => {
                    this.hideLoading();
                    this.isLoading = false;
                })
                .catch((error) => {
                    this.showError(error.message || 'Failed to load analytics data');
                    this.isLoading = false;
                });
        }

        /**
         * Load overview analytics data
         */
        loadOverviewData() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: chatshop_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'chatshop_get_analytics_data',
                        nonce: chatshop_admin.nonce,
                        date_range: this.currentDateRange,
                        metric_type: 'overview'
                    },
                    success: (response) => {
                        if (response.success) {
                            this.updateOverviewCards(response.data);
                            this.renderRevenueConversionsChart(response.data.daily_breakdown);
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data.message || 'Failed to load overview data'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`AJAX Error: ${error}`));
                    }
                });
            });
        }

        /**
         * Load conversion statistics
         */
        loadConversionStats() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: chatshop_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'chatshop_get_conversion_stats',
                        nonce: chatshop_admin.nonce,
                        date_range: this.currentDateRange
                    },
                    success: (response) => {
                        if (response.success) {
                            this.updateConversionFunnel(response.data.funnel);
                            this.updateGatewayPerformance(response.data.gateway_performance);
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data.message || 'Failed to load conversion stats'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`AJAX Error: ${error}`));
                    }
                });
            });
        }

        /**
         * Load revenue attribution data
         */
        loadRevenueAttribution() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: chatshop_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'chatshop_get_revenue_attribution',
                        nonce: chatshop_admin.nonce,
                        date_range: this.currentDateRange
                    },
                    success: (response) => {
                        if (response.success) {
                            this.renderRevenueAttributionChart(response.data.by_source);
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data.message || 'Failed to load revenue attribution'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`AJAX Error: ${error}`));
                    }
                });
            });
        }

        /**
         * Load performance metrics
         */
        loadPerformanceMetrics() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: chatshop_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'chatshop_get_performance_metrics',
                        nonce: chatshop_admin.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            this.updateGrowthIndicators(response.data.growth_rates);
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data.message || 'Failed to load performance metrics'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error(`AJAX Error: ${error}`));
                    }
                });
            });
        }

        /**
         * Update overview cards with data
         * @param {Object} data Overview data
         */
        updateOverviewCards(data) {
            const totals = data.totals || {};
            
            $('#total-revenue').text(this.formatCurrency(totals.revenue || 0));
            $('#total-conversions').text(this.formatNumber(totals.conversions || 0));
            $('#whatsapp-interactions').text(this.formatNumber(totals.interactions || 0));
            $('#conversion-rate').text(data.conversion_rate + '%');
        }

        /**
         * Update growth indicators
         * @param {Object} growthRates Growth rate data
         */
        updateGrowthIndicators(growthRates) {
            this.updateGrowthIndicator('#revenue-growth', growthRates.revenue_growth);
            this.updateGrowthIndicator('#conversion-growth', growthRates.conversion_growth);
        }

        /**
         * Update individual growth indicator
         * @param {string} selector Element selector
         * @param {number} growth Growth percentage
         */
        updateGrowthIndicator(selector, growth) {
            const $element = $(selector);
            const isPositive = growth >= 0;
            
            $element
                .text((isPositive ? '+' : '') + growth + '%')
                .removeClass('positive negative')
                .addClass(isPositive ? 'positive' : 'negative');
        }

        /**
         * Render revenue and conversions chart
         * @param {Array} dailyData Daily breakdown data
         */
        renderRevenueConversionsChart(dailyData) {
            const canvas = document.getElementById('revenue-conversions-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const container = canvas.parentElement;
            
            // Set canvas size
            canvas.width = container.offsetWidth;
            canvas.height = container.offsetHeight;

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (!dailyData || dailyData.length === 0) {
                this.drawNoDataMessage(ctx, canvas.width, canvas.height);
                return;
            }

            // Prepare data
            const revenues = dailyData.map(d => parseFloat(d.daily_revenue || 0));
            const conversions = dailyData.map(d => parseInt(d.daily_conversions || 0));
            const labels = dailyData.map(d => this.formatDate(d.metric_date));

            // Draw chart
            this.drawLineChart(ctx, {
                width: canvas.width,
                height: canvas.height,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenues,
                        color: '#135e96',
                        yAxis: 'left'
                    },
                    {
                        label: 'Conversions',
                        data: conversions,
                        color: '#00a32a',
                        yAxis: 'right'
                    }
                ],
                labels: labels
            });
        }

        /**
         * Update conversion funnel
         * @param {Object} funnelData Funnel data
         */
        updateConversionFunnel(funnelData) {
            if (!funnelData) return;

            const steps = [
                { key: 'messages_sent', count: parseInt(funnelData.messages_sent || 0) },
                { key: 'messages_opened', count: parseInt(funnelData.messages_opened || 0) },
                { key: 'links_clicked', count: parseInt(funnelData.links_clicked || 0) },
                { key: 'payments_initiated', count: parseInt(funnelData.payments_initiated || 0) },
                { key: 'payments_completed', count: parseInt(funnelData.payments_completed || 0) }
            ];

            const maxCount = Math.max(...steps.map(s => s.count));

            steps.forEach((step, index) => {
                const percentage = maxCount > 0 ? (step.count / maxCount) * 100 : 0;
                const countId = step.key.replace('_', '-') + '-count';
                
                $(`#${countId}`).text(this.formatNumber(step.count));
                $(`.funnel-step[data-step="${step.key}"] .funnel-fill`).css('width', percentage + '%');
            });
        }

        /**
         * Update gateway performance table
         * @param {Array} gatewayData Gateway performance data
         */
        updateGatewayPerformance(gatewData) {
            const $container = $('#gateway-performance-list');
            $container.empty();

            if (!gatewayData || gatewayData.length === 0) {
                $container.html('<div class="no-data">No gateway data available</div>');
                return;
            }

            gatewayData.forEach(gateway => {
                const successRate = gateway.total_attempts > 0 
                    ? ((gateway.successful_payments / gateway.total_attempts) * 100).toFixed(1)
                    : 0;
                
                const rateClass = successRate >= 80 ? 'high' : successRate >= 60 ? 'medium' : 'low';
                
                const $item = $(`
                    <div class="performance-item">
                        <div class="gateway-name">${gateway.gateway || 'Unknown'}</div>
                        <div class="success-rate ${rateClass}">${successRate}%</div>
                        <div class="avg-value">${this.formatCurrency(gateway.avg_revenue || 0)}</div>
                        <div class="total-revenue">${this.formatCurrency((gateway.successful_payments * gateway.avg_revenue) || 0)}</div>
                    </div>
                `);
                
                $container.append($item);
            });
        }

        /**
         * Render revenue attribution pie chart
         * @param {Array} attributionData Attribution data by source
         */
        renderRevenueAttributionChart(attributionData) {
            const canvas = document.getElementById('revenue-attribution-chart');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const container = canvas.parentElement;
            
            // Set canvas size to square
            const size = Math.min(container.offsetWidth, 300);
            canvas.width = size;
            canvas.height = size;

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (!attributionData || attributionData.length === 0) {
                this.drawNoDataMessage(ctx, canvas.width, canvas.height);
                return;
            }

            // Prepare data
            const total = attributionData.reduce((sum, item) => sum + parseFloat(item.total_revenue || 0), 0);
            const colors = ['#135e96', '#00a32a', '#dba617', '#d63638', '#8b5cf6', '#f59e0b'];
            
            // Draw pie chart
            this.drawPieChart(ctx, {
                width: canvas.width,
                height: canvas.height,
                data: attributionData.map((item, index) => ({
                    label: item.source_type || 'Unknown',
                    value: parseFloat(item.total_revenue || 0),
                    color: colors[index % colors.length]
                })),
                total: total
            });

            // Update legend
            this.updateAttributionLegend(attributionData, colors);
        }

        /**
         * Update attribution legend
         * @param {Array} data Attribution data
         * @param {Array} colors Color array
         */
        updateAttributionLegend(data, colors) {
            const $legend = $('#attribution-legend');
            $legend.empty();

            data.forEach((item, index) => {
                const $item = $(`
                    <div class="attribution-item">
                        <div class="attribution-color" style="background-color: ${colors[index % colors.length]}"></div>
                        <span>${item.source_type || 'Unknown'}: ${this.formatCurrency(item.total_revenue || 0)}</span>
                    </div>
                `);
                $legend.append($item);
            });
        }

        /**
         * Draw line chart with vanilla JavaScript
         * @param {CanvasRenderingContext2D} ctx Canvas context
         * @param {Object} options Chart options
         */
        drawLineChart(ctx, options) {
            const { width, height, datasets, labels } = options;
            const padding = 60;
            const chartWidth = width - (padding * 2);
            const chartHeight = height - (padding * 2);

            // Draw background
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);

            if (!datasets || datasets.length === 0) return;

            // Get data ranges
            const leftDataset = datasets.find(d => d.yAxis === 'left');
            const rightDataset = datasets.find(d => d.yAxis === 'right');

            const leftMax = leftDataset ? Math.max(...leftDataset.data) : 0;
            const rightMax = rightDataset ? Math.max(...rightDataset.data) : 0;

            // Draw grid lines
            ctx.strokeStyle = '#f0f0f1';
            ctx.lineWidth = 1;

            for (let i = 0; i <= 5; i++) {
                const y = padding + (chartHeight / 5) * i;
                ctx.beginPath();
                ctx.moveTo(padding, y);
                ctx.lineTo(width - padding, y);
                ctx.stroke();
            }

            // Draw axes
            ctx.strokeStyle = '#c3c4c7';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(padding, padding);
            ctx.lineTo(padding, height - padding);
            ctx.lineTo(width - padding, height - padding);
            ctx.stroke();

            // Draw datasets
            datasets.forEach(dataset => {
                const isLeft = dataset.yAxis === 'left';
                const maxValue = isLeft ? leftMax : rightMax;
                
                if (maxValue === 0) return;

                ctx.strokeStyle = dataset.color;
                ctx.fillStyle = dataset.color + '20';
                ctx.lineWidth = 3;

                // Draw line
                ctx.beginPath();
                dataset.data.forEach((value, index) => {
                    const x = padding + (chartWidth / (dataset.data.length - 1)) * index;
                    const y = height - padding - (value / maxValue) * chartHeight;
                    
                    if (index === 0) {
                        ctx.moveTo(x, y);
                    } else {
                        ctx.lineTo(x, y);
                    }
                });
                ctx.stroke();

                // Draw points
                ctx.fillStyle = dataset.color;
                dataset.data.forEach((value, index) => {
                    const x = padding + (chartWidth / (dataset.data.length - 1)) * index;
                    const y = height - padding - (value / maxValue) * chartHeight;
                    
                    ctx.beginPath();
                    ctx.arc(x, y, 4, 0, 2 * Math.PI);
                    ctx.fill();
                });
            });

            // Draw labels
            ctx.fillStyle = '#646970';
            ctx.font = '12px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'center';

            labels.forEach((label, index) => {
                const x = padding + (chartWidth / (labels.length - 1)) * index;
                ctx.fillText(label, x, height - padding + 20);
            });

            // Draw Y-axis labels
            ctx.textAlign = 'right';
            if (leftDataset && leftMax > 0) {
                for (let i = 0; i <= 5; i++) {
                    const value = (leftMax / 5) * (5 - i);
                    const y = padding + (chartHeight / 5) * i;
                    ctx.fillText(this.formatCurrency(value), padding - 10, y + 4);
                }
            }

            ctx.textAlign = 'left';
            if (rightDataset && rightMax > 0) {
                for (let i = 0; i <= 5; i++) {
                    const value = (rightMax / 5) * (5 - i);
                    const y = padding + (chartHeight / 5) * i;
                    ctx.fillText(this.formatNumber(value), width - padding + 10, y + 4);
                }
            }
        }

        /**
         * Draw pie chart with vanilla JavaScript
         * @param {CanvasRenderingContext2D} ctx Canvas context
         * @param {Object} options Chart options
         */
        drawPieChart(ctx, options) {
            const { width, height, data, total } = options;
            const centerX = width / 2;
            const centerY = height / 2;
            const radius = Math.min(width, height) / 2 - 20;

            if (total === 0) {
                this.drawNoDataMessage(ctx, width, height);
                return;
            }

            let currentAngle = -Math.PI / 2; // Start at top

            data.forEach(item => {
                const sliceAngle = (item.value / total) * 2 * Math.PI;
                
                // Draw slice
                ctx.fillStyle = item.color;
                ctx.beginPath();
                ctx.moveTo(centerX, centerY);
                ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
                ctx.closePath();
                ctx.fill();

                // Draw slice border
                ctx.strokeStyle = '#ffffff';
                ctx.lineWidth = 2;
                ctx.stroke();

                currentAngle += sliceAngle;
            });

            // Draw center circle for donut effect
            ctx.fillStyle = '#ffffff';
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius * 0.4, 0, 2 * Math.PI);
            ctx.fill();

            // Draw total in center
            ctx.fillStyle = '#1d2327';
            ctx.font = 'bold 16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Total', centerX, centerY - 8);
            ctx.font = '14px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.fillText(this.formatCurrency(total), centerX, centerY + 8);
        }

        /**
         * Draw "No Data" message
         * @param {CanvasRenderingContext2D} ctx Canvas context
         * @param {number} width Canvas width
         * @param {number} height Canvas height
         */
        drawNoDataMessage(ctx, width, height) {
            ctx.fillStyle = '#f6f7f7';
            ctx.fillRect(0, 0, width, height);
            
            ctx.fillStyle = '#646970';
            ctx.font = '16px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('No data available', width / 2, height / 2);
        }

        /**
         * Export analytics data
         */
        exportAnalytics() {
            const $button = $('#export-analytics');
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Exporting...');

            $.ajax({
                url: chatshop_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'chatshop_export_analytics',
                    nonce: chatshop_admin.nonce,
                    date_range: this.currentDateRange,
                    format: 'csv'
                },
                success: (response) => {
                    if (response.success) {
                        // Create download link
                        const blob = new Blob([response.data.content], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename || 'chatshop-analytics.csv';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                    } else {
                        alert(response.data.message || 'Export failed');
                    }
                },
                error: () => {
                    alert('Export failed. Please try again.');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        }

        /**
         * Resize charts on window resize
         */
        resizeCharts() {
            // Trigger chart re-rendering with current data
            this.loadAnalytics(false);
        }

        /**
         * Show loading indicator
         */
        showLoading() {
            $('#analytics-loading').show();
            $('#analytics-error').hide();
            $('.chatshop-analytics-overview, .chatshop-analytics-charts').css('opacity', '0.5');
        }

        /**
         * Hide loading indicator
         */
        hideLoading() {
            $('#analytics-loading').hide();
            $('.chatshop-analytics-overview, .chatshop-analytics-charts').css('opacity', '1');
        }

        /**
         * Show error message
         * @param {string} message Error message
         */
        showError(message) {
            $('#analytics-loading').hide();
            $('#analytics-error p').text(message);
            $('#analytics-error').show();
        }

        /**
         * Format currency value
         * @param {number} value Numeric value
         * @returns {string} Formatted currency
         */
        formatCurrency(value) {
            return new Intl.NumberFormat('en-NG', {
                style: 'currency',
                currency: 'NGN',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(value);
        }

        /**
         * Format number with separators
         * @param {number} value Numeric value
         * @returns {string} Formatted number
         */
        formatNumber(value) {
            return new Intl.NumberFormat('en-NG').format(value);
        }

        /**
         * Format date for display
         * @param {string} dateString Date string
         * @returns {string} Formatted date
         */
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        /**
         * Debounce utility function
         * @param {Function} func Function to debounce
         * @param {number} wait Wait time in milliseconds
         * @returns {Function} Debounced function
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    }

    // Initialize analytics dashboard when document is ready
    $(document).ready(function() {
        // Only initialize if we're on the analytics page
        if ($('.chatshop-analytics-dashboard').length > 0) {
            window.ChatShopAnalytics = new ChatShopAnalytics();
        }
    });

})(jQuery);