// 页面加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    // 初始化所有功能
    initSidebar();
    initFormValidation();
    initAnimations();
    initTooltips();
    initTableSorting();
    initModalWindows();
});

// 初始化侧边栏功能
function initSidebar() {
    const sidebarLinks = document.querySelectorAll('.sidebar ul li a');
    
    // 添加点击事件监听
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // 移除所有链接的active类
            sidebarLinks.forEach(item => item.classList.remove('active'));
            // 添加当前链接的active类
            this.classList.add('active');
        });
    });
    
    // 移动端侧边栏切换
    const toggleSidebar = document.querySelector('.toggle-sidebar');
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('collapsed');
        });
    }
}

// 初始化表单验证
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    field.nextElementSibling && field.nextElementSibling.classList.add('error-message');
                } else {
                    field.classList.remove('error');
                    field.nextElementSibling && field.nextElementSibling.classList.remove('error-message');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('请填写所有必填字段', 'error');
            }
        });
        
        // 输入框焦点事件
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.classList.remove('focused');
            });
        });
    });
}

// 初始化动画效果
function initAnimations() {
    // 统计卡片动画
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * index);
    });
    
    // 表格行悬停效果
    const tableRows = document.querySelectorAll('table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(22, 93, 255, 0.02)';
            this.style.transition = 'background-color 0.2s ease';
        });
        
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'transparent';
        });
    });
    
    // 按钮悬停效果
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-1px)';
            this.style.transition = 'transform 0.2s ease, box-shadow 0.2s ease';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

// 初始化工具提示
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = tooltipText;
            tooltip.style.position = 'absolute';
            tooltip.style.backgroundColor = 'rgba(0, 0, 0, 0.8)';
            tooltip.style.color = 'white';
            tooltip.style.padding = '6px 12px';
            tooltip.style.borderRadius = '4px';
            tooltip.style.fontSize = '12px';
            tooltip.style.zIndex = '1000';
            tooltip.style.pointerEvents = 'none';
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltipRect.width / 2}px`;
            tooltip.style.top = `${rect.top - tooltipRect.height - 10}px`;
            
            this.addEventListener('mouseleave', function() {
                document.body.removeChild(tooltip);
            });
        });
    });
}

// 初始化表格排序
function initTableSorting() {
    const tables = document.querySelectorAll('table');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            header.addEventListener('click', function() {
                // 切换排序状态
                const isAscending = this.classList.toggle('ascending');
                
                // 移除其他表头的排序状态
                headers.forEach(h => {
                    if (h !== this) {
                        h.classList.remove('ascending', 'descending');
                    }
                });
                
                // 添加当前表头的排序状态
                this.classList.add(isAscending ? 'ascending' : 'descending');
                
                // 排序表格行
                sortTable(table, index, isAscending);
            });
        });
    });
}

// 表格排序函数
function sortTable(table, columnIndex, isAscending) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // 排序行
    rows.sort((a, b) => {
        const aValue = a.querySelectorAll('td')[columnIndex].textContent.trim();
        const bValue = b.querySelectorAll('td')[columnIndex].textContent.trim();
        
        // 数字比较
        if (!isNaN(aValue) && !isNaN(bValue)) {
            return isAscending ? parseFloat(aValue) - parseFloat(bValue) : parseFloat(bValue) - parseFloat(aValue);
        }
        
        // 字符串比较
        return isAscending ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
    });
    
    // 重新添加行
    rows.forEach(row => tbody.appendChild(row));
}

// 初始化模态窗口
function initModalWindows() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            
            if (modal) {
                // 使用 flex 以居中显示
                modal.style.display = 'flex';
                modal.style.opacity = '0';
                modal.style.transition = 'opacity 0.3s ease';
                
                setTimeout(() => {
                    modal.style.opacity = '1';
                }, 10);
                
                // 关闭模态窗口
                const closeBtn = modal.querySelector('.close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        closeModal(modal);
                    });
                }
                
                // 点击外部关闭
                window.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal(modal);
                    }
                });
            }
        });
    });
}

// 关闭模态窗口
function closeModal(modal) {
    modal.style.opacity = '0';
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

// 显示通知
function showNotification(message, type = 'info') {
    // 创建通知元素
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // 设置样式
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '12px 24px';
    notification.style.borderRadius = '8px';
    notification.style.color = 'white';
    notification.style.fontSize = '14px';
    notification.style.fontWeight = '500';
    notification.style.zIndex = '10000';
    notification.style.opacity = '0';
    notification.style.transform = 'translateX(100%)';
    notification.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    
    // 根据类型设置背景色
    switch (type) {
        case 'success':
            notification.style.backgroundColor = '#00B42A';
            break;
        case 'error':
            notification.style.backgroundColor = '#F53F3F';
            break;
        case 'warning':
            notification.style.backgroundColor = '#FF7D00';
            break;
        default:
            notification.style.backgroundColor = '#165DFF';
    }
    
    // 添加到页面
    document.body.appendChild(notification);
    
    // 显示通知
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    // 3秒后隐藏通知
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        
        // 移除通知元素
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// 表单提交处理
function submitForm(form, successCallback, errorCallback) {
    const formData = new FormData(form);
    
    // 模拟表单提交
    setTimeout(() => {
        // 这里可以替换为实际的AJAX请求
        successCallback && successCallback();
    }, 1000);
    
    return false;
}

// 数字格式化
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// 日期格式化
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// 显示加载动画
function showLoading() {
    const loading = document.createElement('div');
    loading.className = 'loading';
    loading.style.position = 'fixed';
    loading.style.top = '0';
    loading.style.left = '0';
    loading.style.width = '100%';
    loading.style.height = '100%';
    loading.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
    loading.style.display = 'flex';
    loading.style.justifyContent = 'center';
    loading.style.alignItems = 'center';
    loading.style.zIndex = '10000';
    
    const spinner = document.createElement('div');
    spinner.style.width = '40px';
    spinner.style.height = '40px';
    spinner.style.border = '4px solid #f3f3f3';
    spinner.style.borderTop = '4px solid #165DFF';
    spinner.style.borderRadius = '50%';
    spinner.style.animation = 'spin 1s linear infinite';
    
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
    document.head.appendChild(style);
    
    loading.appendChild(spinner);
    document.body.appendChild(loading);
    
    return loading;
}

// 隐藏加载动画
function hideLoading(loading) {
    if (loading) {
        loading.style.opacity = '0';
        loading.style.transition = 'opacity 0.3s ease';
        
        setTimeout(() => {
            document.body.removeChild(loading);
        }, 300);
    }
}

// 平滑滚动到元素
function scrollToElement(element) {
    element.scrollIntoView({ behavior: 'smooth' });
}

// 复制到剪贴板
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showNotification('复制成功', 'success');
    }).catch(function() {
        showNotification('复制失败', 'error');
    });
}

// 检查浏览器兼容性
function checkBrowserCompatibility() {
    const features = {
        flexbox: 'flex' in document.documentElement.style,
        cssVariables: 'CSS' in window && 'supports' in window.CSS && window.CSS.supports('--a', 0),
        fetch: 'fetch' in window,
        promises: 'Promise' in window
    };
    
    // 记录兼容性信息
    console.log('浏览器兼容性检查:', features);
    
    return features;
}
