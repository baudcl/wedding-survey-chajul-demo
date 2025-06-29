// Configuration centralisée du thème du site de mariage
const themeConfig = {
    // Couleurs principales
    colors: {
        primary: '#040136',      // Bleu foncé - couleur principale
        secondary: '#FDB0C0',    // Rose poudré - couleur secondaire
        accent: '#323C60',       // Bleu marine doux - couleur d'accent
        background:  '#FFD6DE',  // Rose clair - fond principal
        backgroundAlt: '#F8F7F2',// Beige -fond alternatif
        text: '#040136',         // Bleu foncé - texte principal
        white: '#FFFFFF',        // Blanc
        border: '#DDDDDD',       // Gris clair - bordures
        error: '#D9534F',        // Rouge - erreurs
        success: '#2E7D32'       // Vert - succès
    },
    
    // Polices
    fonts: {
        // Police principale pour les titres décoratifs
        decorative: {
            name: 'RTL-Adam Script',
            fallback: 'cursive',
            weight: 'normal'
        },
        // Police pour les titres secondaires
        heading: {
            name: 'Lato',
            fallback: 'serif',
            weight: '400'
        },
        // Police pour le corps du texte
        body: {
            name: 'Forum',
            fallback: 'serif',
            weight: 'normal'
        },
        // Police alternative
        alternative: {
            name: 'Montserrat',
            fallback: 'sans-serif',
            weight: '300, 400, 600'
        }
    },
    
    // Tailles de police
    fontSize: {
        // Titres principaux
        h1: {
            desktop: '8rem',
            tablet: '4rem',
            mobile: '2.5rem'
        },
        h2: {
            desktop: '3rem',
            tablet: '2rem',
            mobile: '1.5rem'
        },
        h3: {
            desktop: '1.5rem',
            tablet: '1.3rem',
            mobile: '1.2rem'
        },
        // Texte normal
        body: {
            desktop: '18px',
            tablet: '16px',
            mobile: '14px'
        },
        // Boutons
        button: {
            desktop: '20px',
            tablet: '18px',
            mobile: '16px'
        }
    },
    
    // Espacements
    spacing: {
        xs: '5px',
        sm: '10px',
        md: '20px',
        lg: '30px',
        xl: '40px',
        xxl: '60px'
    },
    
    // Bordures et arrondis
    borders: {
        radius: {
            none: '0px',
            small: '5px',
            medium: '10px',
            large: '20px',
            full: '50%'
        },
        width: {
            thin: '1px',
            medium: '2px',
            thick: '3px'
        }
    },
    
    // Ombres
    shadows: {
        small: '0 2px 5px rgba(0, 0, 0, 0.1)',
        medium: '0 5px 15px rgba(0, 0, 0, 0.1)',
        large: '0 10px 30px rgba(0, 0, 0, 0.15)',
        glow: '0 0 20px rgba(0, 0, 0, 0.1)'
    },
    
    // Transitions
    transitions: {
        fast: '0.2s ease',
        normal: '0.3s ease',
        slow: '0.5s ease'
    },
    
    // Points de rupture responsive
    breakpoints: {
        mobile: '568px',
        tablet: '768px',
        desktop: '1024px',
        wide: '1200px'
    }
};

// Fonction pour appliquer le thème au document
function applyTheme() {
    const root = document.documentElement;
    
    // Appliquer les variables CSS personnalisées pour les couleurs
    Object.entries(themeConfig.colors).forEach(([key, value]) => {
        root.style.setProperty(`--color-${key}`, value);
    });
    
    // Appliquer les variables CSS pour les polices
    Object.entries(themeConfig.fonts).forEach(([key, font]) => {
        root.style.setProperty(`--font-${key}`, `'${font.name}', ${font.fallback}`);
        root.style.setProperty(`--font-weight-${key}`, font.weight);
    });
    
    // Appliquer les variables CSS pour les espacements
    Object.entries(themeConfig.spacing).forEach(([key, value]) => {
        root.style.setProperty(`--spacing-${key}`, value);
    });
    
    // Appliquer les variables CSS pour les bordures
    Object.entries(themeConfig.borders.radius).forEach(([key, value]) => {
        root.style.setProperty(`--radius-${key}`, value);
    });
    
    Object.entries(themeConfig.borders.width).forEach(([key, value]) => {
        root.style.setProperty(`--border-${key}`, value);
    });
    
    // Appliquer les variables CSS pour les ombres
    Object.entries(themeConfig.shadows).forEach(([key, value]) => {
        root.style.setProperty(`--shadow-${key}`, value);
    });
    
    // Appliquer les variables CSS pour les transitions
    Object.entries(themeConfig.transitions).forEach(([key, value]) => {
        root.style.setProperty(`--transition-${key}`, value);
    });
}

// Fonction pour obtenir une valeur de configuration
function getThemeValue(path) {
    const keys = path.split('.');
    let value = themeConfig;
    
    for (const key of keys) {
        value = value[key];
        if (value === undefined) return null;
    }
    
    return value;
}

// Appliquer le thème au chargement du document
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTheme);
    } else {
        applyTheme();
    }
}

// Exporter pour utilisation dans d'autres scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { themeConfig, applyTheme, getThemeValue };
}