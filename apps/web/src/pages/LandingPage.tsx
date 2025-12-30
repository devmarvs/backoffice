import { useNavigate } from 'react-router-dom'

export function LandingPage() {
  const navigate = useNavigate()

  return (
    <div className="landing">
      <header className="landing-header">
        <div className="brand">
          <img src="/logo.png" alt="BackOffice Autopilot" className="brand-logo" />
          <div>
            <p className="brand-title">BackOffice</p>
            <p className="brand-subtitle">Autopilot for Tutors</p>
          </div>
        </div>
        <div className="landing-actions">
          <button className="button button--ghost" onClick={() => navigate('/login')}>
            Sign in
          </button>
          <button className="button button--primary" onClick={() => navigate('/onboarding')}>
            Start autopilot
          </button>
        </div>
      </header>

      <section className="landing-hero">
        <div className="landing-hero-copy">
          <p className="eyebrow">The Solo Tutor's Secret Weapon</p>
          <h1>Log a session in 5 seconds. Reclaim your evenings.</h1>
          <p className="muted">
            BackOffice Autopilot handles the administration you dread. From instant invoice drafts to automatic follow-up reminders, we keep your business moving while you focus on teaching.
          </p>
          <div className="landing-actions">
            <button className="button button--primary" onClick={() => navigate('/onboarding')}>
              Start your free setup
            </button>
            <button className="button button--ghost" onClick={() => navigate('/login')}>
              Sign in to your office
            </button>
          </div>
        </div>
        <div className="landing-hero-card">
          <div className="hero-image-container">
            <img src="/hero_tutor_desk.png" alt="Clean tutor workspace" />
          </div>
          <p className="eyebrow">Autopilot in action</p>
          <ul className="list">
            <li>
              <span>Session Logged: Advanced Calculus</span>
              <strong style={{ color: 'var(--accent)' }}>Done</strong>
            </li>
            <li>
              <span>Invoice Drafted ($85.00)</span>
              <strong style={{ color: 'var(--accent)' }}>Done</strong>
            </li>
            <li>
              <span>Follow-up scheduled for Friday</span>
              <strong style={{ color: 'var(--accent)' }}>Wait</strong>
            </li>
          </ul>
        </div>
      </section>

      <section className="trusted-by">
        <p className="eyebrow">Powering independent educators worldwide</p>
        <div className="logo-cloud">
          <div className="logo-item">MathMasters</div>
          <div className="logo-item">PianoPro</div>
          <div className="logo-item">LanguageLift</div>
          <div className="logo-item">CodingCrate</div>
        </div>
      </section>

      <section>
        <div className="landing-section-title">
          <p className="eyebrow">Workflow</p>
          <h2>How it works</h2>
          <p className="muted">Three steps to a more organized business.</p>
        </div>
        <div className="how-it-works">
          <div className="card step-card">
            <div className="step-number">1</div>
            <h3>Log the Event</h3>
            <p className="muted">One-click logging for sessions, no-shows, or prep time. No cluttered spreadsheets.</p>
          </div>
          <div className="card step-card">
            <div className="step-number">2</div>
            <h3>Autopilot Kicks In</h3>
            <p className="muted">We instantly draft your invoice, track package usage, and queue follow-up messages.</p>
          </div>
          <div className="card step-card">
            <div className="step-number">3</div>
            <h3>Get Paid & Stay Consistent</h3>
            <p className="muted">Review and send invoices in bulk. Never miss a follow-up or a payment again.</p>
          </div>
        </div>
      </section>

      <section className="landing-grid">
        <div className="card">
          <h3>Capture Work Events</h3>
          <p className="muted">Session, no-show, admin tasks. Minimal input, maximum output.</p>
        </div>
        <div className="card">
          <h3>Draft invoices instantly</h3>
          <p className="muted">Always know what is billable and ready to send.</p>
        </div>
        <div className="card">
          <h3>Follow-up cues</h3>
          <p className="muted">No more forgotten messages after sessions.</p>
        </div>
        <div className="card">
          <h3>Package tracking</h3>
          <p className="muted">Know how many sessions are left before you deliver them.</p>
        </div>
      </section>

      <section>
        <div className="landing-section-title">
          <p className="eyebrow">Social Proof</p>
          <h2>Loved by Tutors</h2>
        </div>
        <div className="testimonials">
          <div className="card testimonial-card">
            <p>"I used to spend 4 hours every Sunday night just doing my billing. Now it's done before I even leave my desk after the last session."</p>
            <div className="testimonial-author">
              <div className="author-avatar">SC</div>
              <div>
                <strong>Sarah Chen</strong>
                <p className="muted">Math & Physics Tutor</p>
              </div>
            </div>
          </div>
          <div className="card testimonial-card">
            <p>"The follow-up reminders are a literal lifesaver. My student retention has increased because I never forget to check in after a tough lesson."</p>
            <div className="testimonial-author">
              <div className="author-avatar">MJ</div>
              <div>
                <strong>Marcus Johnson</strong>
                <p className="muted">LSAT Prep Coach</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="landing-pricing">
        <div className="card pricing-card text-center">
          <div>
            <p className="eyebrow">Simple Pricing</p>
            <h2>$29/mo</h2>
            <p className="muted">Everything you need to run your back office on autopilot.</p>
          </div>
          <ul className="list">
            <li><span>Unlimited Clients & Events</span><strong>✓</strong></li>
            <li><span>Automatic Invoice Drafting</span><strong>✓</strong></li>
            <li><span>Smart Follow-up Queue</span><strong>✓</strong></li>
            <li><span>Package & Credit Tracking</span><strong>✓</strong></li>
          </ul>
          <button className="button button--primary" onClick={() => navigate('/onboarding')}>
            Get Started Now
          </button>
        </div>
      </section>

      <section className="landing-cta">
        <div>
          <h2>Ready to feel caught up?</h2>
          <p className="muted">
            Join 500+ tutors who have automated their administration.
          </p>
        </div>
        <button className="button button--primary" onClick={() => navigate('/onboarding')}>
          Start in 2 minutes
        </button>
      </section>
    </div>
  )
}
