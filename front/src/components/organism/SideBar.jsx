import React from "react";
import "bootstrap-icons/font/bootstrap-icons.min.css";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import {
    faBars,
    faListCheck,
    faChartLine,
    faEnvelope,
} from "@fortawesome/free-solid-svg-icons";
import { Button, Title } from "../atoms";
import { Link } from "react-router-dom";

const SideBar = () => {
    return (
        <nav
            id="sidebar"
            className="bg-dark text-white"
            aria-label="Main Navigation"
            style={{ width: "250px", minHeight: "100vh" }}
        >
            <div className="js-sidebar-scroll">
                <div className="content-side py-4 px-3">
                    <div className="d-flex align-items-center mb-4">
                        <i
                            className="bi bi-person-circle text-white me-2"
                            style={{ fontSize: "8rem" }}
                            aria-hidden="true"
                        ></i>
                    </div>
                    <ul className="nav-main list-unstyled">
                        <li className="nav-main-item mb-2">
                            <Link to="/">
                                <Button className="btn btn-dark w-100 text-start py-2">
                                    <span className="d-inline-flex align-items-center">
                                        <FontAwesomeIcon icon={faBars} />
                                        <Title className="ms-2 mb-0">
                                            Dashboard
                                        </Title>
                                    </span>
                                </Button>
                            </Link>
                        </li>

                        <li class="nav-main-item mb-2">
                            <Link to="/goal-list">
                                <Button
                                    className="btn btn-dark w-100 text-start py-2"
                                    onClick={() => {}}
                                >
                                    <span className="d-inline-flex align-items-center">
                                        <FontAwesomeIcon icon={faListCheck} />
                                        <Title className="ms-2 mb-0">
                                            Objectifs
                                        </Title>
                                    </span>
                                </Button>
                            </Link>
                        </li>

                        <li class="nav-main-item mb-2">
                            <Button
                                className="btn btn-dark w-100 text-start py-2"
                                onClick={() => {}}
                            >
                                <span className="d-inline-flex align-items-center">
                                    <FontAwesomeIcon icon={faChartLine} />{" "}
                                    <Title className="ms-2 mb-0">
                                        {" "}
                                        Statistiques{" "}
                                    </Title>
                                </span>
                            </Button>
                        </li>

                        <li class="nav-main-item mb-2">
                            <Button
                                className="btn btn-dark w-100 text-start py-2"
                                onClick={() => {}}
                            >
                                <span className="d-inline-flex align-items-center">
                                    <FontAwesomeIcon icon={faEnvelope} />{" "}
                                    <Title className="ms-2 mb-0">
                                        {" "}
                                        Notifications{" "}
                                    </Title>
                                </span>
                            </Button>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    );
};

export default SideBar;
