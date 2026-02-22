# Franklin Air Arkansas

> Professional marketing website for Franklin Air Arkansas — a family-owned HVAC service company.

![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)
![Deploy](https://github.com/TristanPutman/FranklinAirArkansas/actions/workflows/deploy.yml/badge.svg)

---

## Features

- **Responsive Design** — Mobile-first layout with desktop flex/grid enhancements
- **Professionals Page** — B2B service page with two-column pricing rows, FAQ, and process steps
- **Scroll Animations** — IntersectionObserver-driven staggered reveals with CSS fallback
- **SEO Optimized** — Open Graph, Twitter Cards, Schema.org structured data (HVACBusiness)
- **Auto-Deploy** — GitHub Actions deploys to FTP on every push to `main`
- **Progressive Enhancement** — Works without JavaScript; no frameworks or build tools
- **PWA Ready** — Web app manifest, full favicon set, theme colors

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Markup | HTML5 (semantic) |
| Styling | CSS3 (custom properties, flexbox, grid) |
| Scripts | Vanilla JS (zero dependencies) |
| Fonts | Google Fonts — DM Serif Display + Nunito Sans |
| Images | Unsplash CDN |
| Hosting | InfinityFree (FTP) |
| CDN/DNS | Cloudflare |
| CI/CD | GitHub Actions → FTP Deploy |

## Project Structure

```
FranklinAirArkansas/
├── .github/workflows/deploy.yml   # Auto-deploy on push
├── css/
│   ├── style.css                  # Main stylesheet
│   └── professionals.css          # Professionals page styles
├── images/
│   └── og-image.png               # Social media preview
├── js/
│   └── main.js                    # Nav toggle, scroll animations, analytics
├── index.html                     # Homepage
├── professionals.html             # HVAC design services page
├── favicon.svg                    # Scalable favicon
├── favicon.ico                    # Legacy favicon (16+32)
├── apple-touch-icon.png           # iOS icon (180x180)
├── android-chrome-*.png           # Android icons (192, 512)
├── site.webmanifest               # PWA manifest
└── .env                           # Credentials (gitignored)
```

## Getting Started

### Prerequisites

- Git
- A text editor
- FTP credentials (stored in `.env` and GitHub Secrets)

### Local Development

```bash
# Clone the repo
git clone git@github.com:TristanPutman/FranklinAirArkansas.git
cd FranklinAirArkansas

# Serve locally (any static server works)
python3 -m http.server 8080
# or
npx serve .
```

Open `http://localhost:8080` to preview.

### Environment Setup

Create a `.env` file in the project root (see `.env.example` pattern):

```env
INFINITYFREE_FTP_USERNAME=your_ftp_user
INFINITYFREE_PASSWORD=your_ftp_pass
INFINITYFREE_FTP_HOST=ftpupload.net
CLOUDFLARE_API_TOKEN=your_token
CLOUDFLARE_ZONE_ID=your_zone_id
```

### Deployment

Deployment is automatic. Push to `main` and GitHub Actions handles the rest:

```bash
git add .
git commit -m "Your changes"
git push origin main
# GitHub Actions deploys to FTP within ~30 seconds
```

The workflow uses [SamKirkland/FTP-Deploy-Action](https://github.com/SamKirkland/FTP-Deploy-Action) to sync files to InfinityFree hosting.

**GitHub Secrets required:**
| Secret | Description |
|--------|-------------|
| `FTP_HOST` | FTP server hostname |
| `FTP_USERNAME` | FTP username |
| `FTP_PASSWORD` | FTP password |

## Design System

| Token | Value | Usage |
|-------|-------|-------|
| `--copper` | `#C45A2D` | Primary accent, buttons, highlights |
| `--navy` | `#1B2A4A` | Headers, navbar, trust elements |
| `--cream` | `#FFF5EB` | Backgrounds, cards |
| `--warm-white` | `#FFFAF5` | Page background |
| `--charcoal` | `#2C2420` | Body text |

**Fonts:** DM Serif Display (headings) · Nunito Sans (body)

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes
4. Push to the branch and open a Pull Request

## License

All rights reserved. &copy; 2026 Franklin Air Arkansas.

## Contact

- **Phone:** (479) 207-2454
- **Email:** info@franklinairarkansas.com
- **Website:** [franklinairarkansas.com](https://franklinairarkansas.com)
