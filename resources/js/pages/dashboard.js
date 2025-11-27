/**
 * Theme: Velonic - Responsive Bootstrap 5 Admin Dashboard
 * Author: Techzaa
 * Module/App: Dashboard
 */

import ApexCharts from 'apexcharts';

// Global function to test dashboard metrics (call from browser console)
window.testDashboardMetrics = function() {
    console.log('Testing dashboard metrics API...');
    fetch('/dashboard-metrics')
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API Response:', data);
            if (data.status === 200 && data.data) {
                console.log('Metrics Data:', data.data);
                alert('API Working! Check console for data.');
            } else {
                alert('API returned unexpected response: ' + JSON.stringify(data));
            }
        })
        .catch(error => {
            console.error('API Error:', error);
            alert('API Error: ' + error.message);
        });
};

! function ($) {
    "use strict";

    var Dashboard = function () {
        this.$body = $("body"),
            this.charts = []
    };

    /**
     * Fetch and display dashboard metrics (Sales, Profit Margin, ROI)
     * Uses same calculation logic as channel-masters page
     * Fetches L30 data from all active channels and calculates metrics
     */
    Dashboard.prototype.fetchDashboardMetrics = function () {
        const self = this;
        
        // Show loading state
        const updateElement = (id, text) => {
            const elem = document.getElementById(id);
            console.log('[Dashboard] Looking for element:', id, '- Found:', !!elem);
            if (elem) {
                elem.textContent = text;
            } else {
                console.warn('[Dashboard] Element not found:', id);
            }
        };
        
        console.log('[Dashboard] Starting fetchDashboardMetrics...');
        updateElement('l30-sales-value', 'Loading...');
        updateElement('profit-margin-value', 'Loading...');
        updateElement('roi-value', 'Loading...');
        updateElement('total-profit-value', 'Loading...');
        
        fetch('/dashboard-metrics')
            .then(response => {
                console.log('[Dashboard] API Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('[Dashboard] API Response data:', data);
                
                if (data.status === 200 && data.data) {
                    const metrics = data.data;
                    console.log('[Dashboard] ✅ Metrics received:', metrics);
                    
                    // Check if using real data or calculated from channels
                    if (metrics.data_found) {
                        console.log('✅ Real data calculated from ' + metrics.channels_count + ' channels (L30 data)');
                    } else {
                        console.warn('⚠️ No real channel data found - showing zeros');
                    }
                    
                    // Update L30 Sales card - format as currency
                    const salesElement = document.getElementById('l30-sales-value');
                    if (salesElement) {
                        const salesValue = metrics.total_sales;
                        let formattedSales;
                        if (salesValue >= 1000) {
                            formattedSales = '$' + (salesValue / 1000).toFixed(1) + 'k';
                        } else {
                            formattedSales = '$' + salesValue.toLocaleString('en-US');
                        }
                        console.log('[Dashboard] Updating L30 Sales to:', formattedSales);
                        salesElement.textContent = formattedSales;
                        if (salesValue === 0) {
                            salesElement.style.opacity = '0.5';
                        }
                    } else {
                        console.warn('[Dashboard] ❌ Element l30-sales-value not found');
                    }
                    
                    // Update Profit Margin card - format as percentage
                    const profitMarginElement = document.getElementById('profit-margin-value');
                    if (profitMarginElement) {
                        const marginValue = metrics.profit_margin;
                        const formattedMargin = marginValue.toFixed(2) + '%';
                        console.log('[Dashboard] Updating Profit Margin to:', formattedMargin);
                        profitMarginElement.textContent = formattedMargin;
                        if (marginValue === 0) {
                            profitMarginElement.style.opacity = '0.5';
                        }
                    } else {
                        console.warn('[Dashboard] ❌ Element profit-margin-value not found');
                    }
                    
                    // Update ROI card - format as percentage
                    const roiElement = document.getElementById('roi-value');
                    if (roiElement) {
                        const roiValue = metrics.roi;
                        const formattedRoi = roiValue.toFixed(2) + '%';
                        console.log('[Dashboard] Updating ROI to:', formattedRoi);
                        roiElement.textContent = formattedRoi;
                        if (roiValue === 0) {
                            roiElement.style.opacity = '0.5';
                        }
                    } else {
                        console.warn('[Dashboard] ❌ Element roi-value not found');
                    }
                    
                    // Update Total Profit card - format as currency
                    const profitElement = document.getElementById('total-profit-value');
                    if (profitElement) {
                        const profitValue = metrics.total_profit;
                        let formattedProfit;
                        if (profitValue >= 1000) {
                            formattedProfit = '$' + (profitValue / 1000).toFixed(1) + 'k';
                        } else {
                            formattedProfit = '$' + profitValue.toLocaleString('en-US');
                        }
                        console.log('[Dashboard] Updating Total Profit to:', formattedProfit);
                        profitElement.textContent = formattedProfit;
                        if (profitValue === 0) {
                            profitElement.style.opacity = '0.5';
                        }
                    } else {
                        console.warn('[Dashboard] ❌ Element total-profit-value not found');
                    }
                    
                    console.log('[Dashboard] ✅ All metrics updated successfully');
                } else {
                    console.warn('[Dashboard] ❌ Unexpected response format:', data);
                    updateElement('l30-sales-value', 'No data');
                    updateElement('profit-margin-value', 'No data');
                    updateElement('roi-value', 'No data');
                    updateElement('total-profit-value', 'No data');
                }
            })
            .catch(error => {
                console.error('[Dashboard] ❌ Error fetching metrics:', error);
                updateElement('l30-sales-value', 'Error');
                updateElement('profit-margin-value', 'Error');
                updateElement('roi-value', 'Error');
                updateElement('total-profit-value', 'Error');
            });
    };


    Dashboard.prototype.initCharts = function () {
        window.Apex = {
            chart: {
                parentHeightOffset: 0,
                toolbar: {
                    show: false
                }
            },
            grid: {
                padding: {
                    left: 0,
                    right: 0
                }
            },
            colors: ["#3e60d5", "#47ad77", "#fa5c7c", "#ffbc00"],
        };

        var colors = ["#3e60d5", "#47ad77", "#fa5c7c", "#ffbc00"];
        var dataColors = $("#revenue-chart").data('colors');
        if (dataColors) {
            colors = dataColors.split(",");
        }

        // Only create revenue chart if element exists
        var revenueChartEl = document.querySelector("#revenue-chart");
        if (revenueChartEl) {
            var options = {
                series: [{
                    name: 'Revenue',
                    data: [440, 505, 414, 526, 227, 413, 201]
                }, {
                    name: 'Sales',
                data: [320, 258, 368, 458, 201, 365, 389]
            }, {
                name: 'Profit',
                data: [320, 458, 369, 520, 180, 369, 160]
            }],
            chart: {
                height: 377,
                type: 'bar'
            },
            plotOptions: {
                bar: {
                    columnWidth: '60%'
                }
            },
            stroke: {
                show: true,
                width: 2,
                colors: ['transparent']
            },
            dataLabels: {
                enabled: false
            },
            colors: colors,
            xaxis: {
                categories: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            },
            yaxis: {
                title: {
                    text: '$ (thousands)'
                }
            },
            legend: {
                offsetY: 7,
            },
            grid: {
                padding: {
                    bottom: 20
                }
            },
            fill: {
                opacity: 1
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return "$ " + val + " thousands"
                    }
                }
            }
        };

            var chart = new ApexCharts(
                document.querySelector("#revenue-chart"),
                options
            );

            chart.render();
        }

        // --------------------------------------------------
        // Only create yearly sales chart if element exists
        var yearlySalesChartEl = document.querySelector("#yearly-sales-chart");
        if (yearlySalesChartEl) {
            var colors = ["#3e60d5", "#47ad77", "#fa5c7c", "#ffbc00"];
            var dataColors = $("#yearly-sales-chart").data('colors');
            if (dataColors) {
                colors = dataColors.split(",");
            }
            var options = {
                series: [
                    {
                        name: "Mobile",
                        data: [25, 15, 25, 36, 32, 42, 45]
                    },
                    {
                        name: "Desktop",
                        data: [20, 10, 20, 31, 27, 37, 40]
                    }
                ],
                chart: {
                    height: 250,
                    type: 'line',
                    toolbar: {
                        show: false
                    }
                },
                colors: colors,

                stroke: {
                    curve: 'smooth',
                    width: [3, 3]
                },
                markers: {
                    size: 3
                },
                xaxis: {
                    categories: ['2017', '2018', '2019', '2020', '2021', '2022', '2023'],
                },
                legend: {
                    show: false
                }
            };

            var chart = new ApexCharts(yearlySalesChartEl, options);
            chart.render();
        }


        /* ------------- visitors by country */
        var usShareChartEl = document.querySelector("#us-share-chart");
        if (usShareChartEl) {
            Apex.grid = {
                padding: {
                    right: 0,
                    left: 0
                }
            }

            Apex.dataLabels = {
                enabled: false
            }
            var options = {
                series: [44, 55, 13, 43],
                chart: {
                    width: 80,
                    type: 'pie',
                },
                legend: {
                    show: false
                },
                colors: ["#1a2942", "#f13c6e", "#3bc0c3", "#d1d7d973"],
                labels: ['Team A', 'Team B', 'Team C', 'Team D'],
            };

            var chart = new ApexCharts(usShareChartEl, options);
            chart.render();
        }
    };

    //initializing various components and plugins
    Dashboard.prototype.init = function () {
        var $this = this;

        // init charts
        this.initCharts();
        
        // Fetch and display dashboard metrics with a slight delay to ensure DOM is ready
        setTimeout(() => {
            this.fetchDashboardMetrics();
        }, 500);
    };

    //init flotchart
    $.Dashboard = new Dashboard, $.Dashboard.Constructor = Dashboard
}(window.jQuery);

//initializing Dashboard
! function ($) {
    "use strict";
    $(document).ready(function (e) {
        $.Dashboard.init();
    });
}(window.jQuery);